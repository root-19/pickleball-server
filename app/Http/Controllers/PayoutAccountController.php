<?php

namespace App\Http\Controllers;

use App\Models\PayoutAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PayoutAccountController extends Controller
{
    public function index(Request $request)
    {
        $accounts = PayoutAccount::where('user_id', $request->user()->id)
            ->orderByDesc('is_primary')
            ->orderBy('created_at')
            ->get();

        return response()->json($accounts);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type'           => 'required|in:gcash,maya,card',
            'account_name'   => 'required_if:type,gcash|required_if:type,maya|nullable|string|max:100',
            'account_number' => 'required_if:type,gcash|required_if:type,maya|nullable|string|max:20',
            'card_holder'    => 'required_if:type,card|nullable|string|max:100',
            'card_last_four' => 'required_if:type,card|nullable|string|size:4',
            'card_expiry'    => 'required_if:type,card|nullable|string|max:7',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $userId = $request->user()->id;
        $isFirst = PayoutAccount::where('user_id', $userId)->count() === 0;

        $account = PayoutAccount::create([
            'user_id'        => $userId,
            'type'           => $request->type,
            'account_name'   => $request->account_name,
            'account_number' => $request->account_number,
            'card_holder'    => $request->card_holder,
            'card_last_four' => $request->card_last_four,
            'card_expiry'    => $request->card_expiry,
            'is_primary'     => $isFirst,
        ]);

        return response()->json($account, 201);
    }

    public function setPrimary(Request $request, $id)
    {
        $userId  = $request->user()->id;
        $account = PayoutAccount::where('user_id', $userId)->findOrFail($id);

        DB::transaction(function () use ($userId, $account) {
            PayoutAccount::where('user_id', $userId)->update(['is_primary' => false]);
            $account->update(['is_primary' => true]);
        });

        return response()->json(['message' => 'Primary payout account updated.']);
    }

    public function destroy(Request $request, $id)
    {
        $account = PayoutAccount::where('user_id', $request->user()->id)->findOrFail($id);
        $wasPrimary = $account->is_primary;
        $account->delete();

        if ($wasPrimary) {
            $next = PayoutAccount::where('user_id', $request->user()->id)->first();
            if ($next) $next->update(['is_primary' => true]);
        }

        return response()->json(['message' => 'Payout account removed.']);
    }
}
