<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BudgetController extends Controller
{
    public function show()
    {
        $budget = Budget::where('user_id', Auth::id())->first();

        if (!$budget) {
            return response()->json(['message' => 'No budget set'], 404);
        }

        return response()->json($this->toPayload($budget));
    }

    public function store(Request $request)
    {
        $amount = $this->amountFromRequest($request);

        $request->validate([
            'amount' => 'nullable',
        ]);
        if (!is_numeric($amount) || $amount < 0) {
            return response()->json(['message' => 'The budget/amount must be a non-negative number.'], 422);
        }

        if (Budget::where('user_id', Auth::id())->exists()) {
            return response()->json(['message' => 'Budget already exists. Please update instead.'], 409);
        }

        $budget = Budget::create([
            'user_id' => Auth::id(),
            'amount'  => $amount,
        ]);

        return response()->json($this->toPayload($budget), 201);
    }

    public function update(Request $request)
    {
        $amount = $this->amountFromRequest($request);

        $request->validate([
            'amount' => 'nullable',
        ]);
        if (!is_numeric($amount) || $amount < 0) {
            return response()->json(['message' => 'The budget/amount must be a non-negative number.'], 422);
        }

        $budget = Budget::where('user_id', Auth::id())->first();

        if (!$budget) {
            return response()->json(['message' => 'No budget found'], 404);
        }

        $budget->update(['amount' => $amount]);

        return response()->json($this->toPayload($budget));
    }

    private function amountFromRequest(Request $request): ?float
    {
        $raw = $request->input('budget', $request->input('amount', null));
        if ($raw === null) return null;
        if (is_numeric($raw)) return (float) $raw;
        if (is_string($raw) && is_numeric(trim($raw))) return (float) trim($raw);
        return null;
    }

    private function toPayload(Budget $budget): array
    {
        return ['budget' => (float) $budget->amount];
    }
}
