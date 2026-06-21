<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Court;
use App\Models\HelpCenterMessage;
use App\Models\Message;
use App\Models\Payout;
use App\Models\PayoutAccount;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    const PLATFORM_FEE = 0.12;

    // ── Auth ──────────────────────────────────────────────────────────────

    public function showLogin()
    {
        if (Auth::check() && Auth::user()->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }
        return view('admin.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            if (Auth::user()->role !== 'admin') {
                Auth::logout();
                return back()->withErrors(['email' => 'Access denied. Admin only.']);
            }
            $request->session()->regenerate();
            return redirect()->route('admin.dashboard');
        }

        return back()->withErrors(['email' => 'Invalid credentials.']);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login');
    }

    // ── Dashboard ─────────────────────────────────────────────────────────

    public function dashboard()
    {
        $totalUsers    = User::where('role', 'user')->count();
        $totalOwners   = User::where('role', 'owner')->count();
        $totalCourts   = Court::count();
        $totalBookings = Booking::where('status', '!=', 'cancelled')->count();

        // Only count paid bookings for revenue (payment_status = paid OR old confirmed without payment)
        $grossRevenue     = (float) Booking::where('status', 'confirmed')
            ->where(function ($q) {
                $q->where('payment_status', 'paid')
                  ->orWhereNull('payment_status')
                  ->orWhere('payment_status', '');
            })->sum('total_price');
        $platformEarnings = round($grossRevenue * self::PLATFORM_FEE, 2);
        $ownerPayouts     = round($grossRevenue * (1 - self::PLATFORM_FEE), 2);

        // Last 30 days daily bookings & revenue for line chart
        $last30 = collect(range(29, 0))->map(function ($daysAgo) {
            $date = Carbon::today()->subDays($daysAgo)->toDateString();
            $dayBookings = Booking::where('booking_date', $date)
                ->where('status', '!=', 'cancelled')->get();
            return [
                'date'     => $date,
                'label'    => Carbon::parse($date)->format('M d'),
                'bookings' => $dayBookings->count(),
                'revenue'  => round((float) $dayBookings->sum('total_price') * (1 - self::PLATFORM_FEE), 2),
                'platform' => round((float) $dayBookings->sum('total_price') * self::PLATFORM_FEE, 2),
            ];
        });

        // Monthly revenue (last 6 months)
        $monthly = collect(range(5, 0))->map(function ($monthsAgo) {
            $start = Carbon::today()->subMonths($monthsAgo)->startOfMonth();
            $end   = $start->copy()->endOfMonth();
            $gross = (float) Booking::whereBetween('booking_date', [$start, $end])
                ->where('status', '!=', 'cancelled')->sum('total_price');
            return [
                'label'    => $start->format('M Y'),
                'gross'    => round($gross, 2),
                'platform' => round($gross * self::PLATFORM_FEE, 2),
                'owners'   => round($gross * (1 - self::PLATFORM_FEE), 2),
            ];
        });

        // New users last 30 days
        $newUsersChart = collect(range(29, 0))->map(function ($daysAgo) {
            $date = Carbon::today()->subDays($daysAgo)->toDateString();
            return [
                'label' => Carbon::parse($date)->format('M d'),
                'count' => User::whereDate('created_at', $date)->count(),
            ];
        });

        // Unread help center messages - get unique users with messages
        $unreadMessages = HelpCenterMessage::with(['user:id,name,email'])
            ->whereNull('admin_id') // Messages not yet replied to by admin
            ->latest()
            ->limit(50)
            ->get()
            ->groupBy('user_id');

        return view('admin.dashboard', compact(
            'totalUsers', 'totalOwners', 'totalCourts', 'totalBookings',
            'grossRevenue', 'platformEarnings', 'ownerPayouts',
            'last30', 'monthly', 'newUsersChart', 'unreadMessages'
        ));
    }

    // ── Users ─────────────────────────────────────────────────────────────

    public function users(Request $request)
    {
        $search = $request->query('search');
        $query  = User::where('role', 'user')->latest();
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }
        $users = $query->paginate(20)->withQueryString();
        return view('admin.users', compact('users', 'search'));
    }

    public function showUser($id)
    {
        $user = User::findOrFail($id);
        $bookings = Booking::with('court')
            ->where('user_id', $id)
            ->latest('booking_date')
            ->get();
        return view('admin.user-detail', compact('user', 'bookings'));
    }

    public function destroyUser($id)
    {
        $user = User::findOrFail($id);
        if ($user->role === 'admin') abort(403);
        $user->delete();
        return back()->with('success', 'User deleted.');
    }

    // ── Owners ────────────────────────────────────────────────────────────

    public function owners(Request $request)
    {
        $search = $request->query('search');
        $query  = User::where('role', 'owner')
            ->withCount('courts' )
            ->latest();
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }
        $owners = $query->paginate(20)->withQueryString();

        $fee = self::PLATFORM_FEE;
        $owners->getCollection()->transform(function ($owner) use ($fee) {
            $courtIds = Court::where('user_id', $owner->id)->pluck('id');
            $gross    = (float) Booking::whereIn('court_id', $courtIds)
                ->where('status', '!=', 'cancelled')->sum('total_price');
            $owner->gross_earnings = round($gross, 2);
            $owner->net_earnings   = round($gross * (1 - $fee), 2);
            $owner->platform_cut   = round($gross * $fee, 2);
            return $owner;
        });

        return view('admin.owners', compact('owners', 'search'));
    }

    public function showOwner($id)
    {
        $owner    = User::where('role', 'owner')->findOrFail($id);
        $courts   = Court::where('user_id', $id)->get();
        $courtIds = $courts->pluck('id');

        $fee       = self::PLATFORM_FEE;
        $bookings  = Booking::with('court')
            ->whereIn('court_id', $courtIds)
            ->where('status', '!=', 'cancelled')
            ->latest('booking_date')->get();

        $gross    = (float) $bookings->sum('total_price');
        $net      = round($gross * (1 - $fee), 2);
        $platform = round($gross * $fee, 2);

        // Monthly earnings chart (last 6 months)
        $earningsChart = collect(range(5, 0))->map(function ($m) use ($courtIds, $fee) {
            $start = Carbon::today()->subMonths($m)->startOfMonth();
            $end   = $start->copy()->endOfMonth();
            $g     = (float) Booking::whereIn('court_id', $courtIds)
                ->whereBetween('booking_date', [$start, $end])
                ->where('status', '!=', 'cancelled')->sum('total_price');
            return [
                'label'  => $start->format('M Y'),
                'gross'  => round($g, 2),
                'net'    => round($g * (1 - $fee), 2),
            ];
        });

        return view('admin.owner-detail', compact(
            'owner', 'courts', 'bookings', 'gross', 'net', 'platform', 'earningsChart'
        ));
    }

    // ── Payouts ───────────────────────────────────────────────────────────

    public function payouts(Request $request)
    {
        $search = $request->query('search');
        $tab    = $request->query('tab', 'owners'); // 'owners' or 'history'
        $fee    = self::PLATFORM_FEE;

        // --- Tab: Owners with booking earnings ---
        $ownerQuery = User::where('role', 'owner')
            ->whereHas('courts.bookings', fn($q) => $q->where('status', 'confirmed'));

        if ($search) {
            $ownerQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $owners = $ownerQuery->with('courts')->get()->map(function ($owner) use ($fee) {
            $courtIds    = $owner->courts->pluck('id');
            $gross       = (float) Booking::whereIn('court_id', $courtIds)
                ->where('status', 'confirmed')->sum('total_price');
            $net         = round($gross * (1 - $fee), 2);
            $alreadyPaid = (float) Payout::where('owner_id', $owner->id)
                ->whereIn('status', ['completed', 'processing'])->sum('net_amount');
            $available   = round($net - $alreadyPaid, 2);
            $lastPayout  = Payout::where('owner_id', $owner->id)->latest()->first();
            $payoutAccount = PayoutAccount::where('user_id', $owner->id)
                ->where('is_primary', true)->first();

            $owner->gross       = $gross;
            $owner->net         = $net;
            $owner->already_paid = $alreadyPaid;
            $owner->available   = max($available, 0);
            $owner->last_payout = $lastPayout;
            $owner->payout_account = $payoutAccount;
            return $owner;
        })->sortByDesc('available')->values();

        // --- Tab: Payout history ---
        $historyQuery = Payout::with(['owner', 'payoutAccount', 'processedBy'])->latest();
        if ($search && $tab === 'history') {
            $historyQuery->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhereHas('owner', fn($q2) => $q2->where('name', 'like', "%{$search}%"));
            });
        }
        $history = $historyQuery->paginate(20)->withQueryString();

        $totalPending   = Payout::where('status', 'pending')->sum('net_amount');
        $totalCompleted = Payout::where('status', 'completed')->sum('net_amount');
        $totalAvailable = $owners->sum('available');

        return view('admin.payouts', compact(
            'owners', 'history', 'search', 'tab',
            'totalPending', 'totalCompleted', 'totalAvailable'
        ));
    }

    public function createPayout(Request $request, $ownerId)
    {
        $owner    = User::where('role', 'owner')->findOrFail($ownerId);
        $courtIds = Court::where('user_id', $ownerId)->pluck('id');

        $fee        = self::PLATFORM_FEE;
        $gross      = (float) Booking::whereIn('court_id', $courtIds)
            ->where('status', 'confirmed')->sum('total_price');
        $alreadyPaid = (float) Payout::where('owner_id', $ownerId)
            ->whereIn('status', ['completed', 'processing'])->sum('net_amount');
        $net        = round($gross * (1 - $fee), 2);
        $available  = round($net - $alreadyPaid, 2);

        $payoutAccounts = PayoutAccount::where('user_id', $ownerId)
            ->orderByDesc('is_primary')->get();

        return view('admin.payout-create', compact(
            'owner', 'gross', 'net', 'available', 'alreadyPaid', 'payoutAccounts'
        ));
    }

    public function storePayout(Request $request, $ownerId)
    {
        $owner = User::where('role', 'owner')->findOrFail($ownerId);

        $request->validate([
            'net_amount'        => 'required|numeric|min:1',
            'payout_account_id' => 'nullable|exists:payout_accounts,id',
            'admin_note'        => 'nullable|string|max:500',
        ]);

        $fee        = self::PLATFORM_FEE;
        $net        = (float) $request->net_amount;
        $gross      = round($net / (1 - $fee), 2);
        $feeAmount  = round($gross * $fee, 2);

        $payout = Payout::create([
            'owner_id'          => $ownerId,
            'payout_account_id' => $request->payout_account_id ?: null,
            'gross_amount'      => $gross,
            'fee_amount'        => $feeAmount,
            'net_amount'        => $net,
            'status'            => 'pending',
            'reference'         => 'PAY-' . strtoupper(Str::random(8)),
            'admin_note'        => $request->admin_note,
        ]);

        return redirect()->route('admin.payouts')->with('success', "Payout ₱" . number_format($net, 2) . " created for {$owner->name}.");
    }

    public function updatePayoutStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|in:pending,processing,completed,failed']);

        $payout = Payout::findOrFail($id);
        $payout->update([
            'status'       => $request->status,
            'processed_by' => Auth::id(),
            'processed_at' => in_array($request->status, ['completed', 'failed']) ? now() : $payout->processed_at,
        ]);

        return back()->with('success', 'Payout status updated to ' . ucfirst($request->status) . '.');
    }

    // ── Messages ─────────────────────────────────────────────────────────

    public function messages(Request $request)
    {
        $search = $request->query('search');
        $query = HelpCenterMessage::with(['user:id,name,email', 'admin:id,name'])
            ->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($q2) => $q2->where('name', 'like', "%{$search}%"));
            });
        }

        $messages = $query->paginate(50)->withQueryString();

        return view('admin.messages', compact('messages', 'search'));
    }

    public function replyMessage(Request $request, $id)
    {
        $request->validate([
            'reply' => 'required|string|max:1000',
        ]);

        $message = HelpCenterMessage::findOrFail($id);

        // Create a new message as the admin's reply
        HelpCenterMessage::create([
            'user_id' => $message->user_id,
            'admin_id' => Auth::id(),
            'message' => $request->reply,
        ]);

        // Mark the original message as replied
        $message->update(['admin_id' => Auth::id()]);

        return back()->with('success', 'Reply sent successfully.');
    }
}
