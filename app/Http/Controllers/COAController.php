<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ChartOfAccounts;
use App\Models\SubHeadOfAccounts;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class COAController extends Controller
{

    public function index(Request $request)
    {
        $subHeadOfAccounts = SubHeadOfAccounts::with('headOfAccount')->get();

        $query = ChartOfAccounts::with('subHeadOfAccount');

        if ($request->filled('subhead') && $request->subhead != 'all') {
            $query->where('shoa_id', $request->subhead);
        }

        $chartOfAccounts = $query->get();

        return view('accounts.coa', compact('chartOfAccounts','subHeadOfAccounts'));
    }

    public function store(Request $request)
    {
        try {
            Log::info('COA Store Method Called', ['user_id' => auth()->id(), 'request' => $request->all()]);

            $validated = $request->validate([
                'shoa_id' => 'required|exists:sub_head_of_accounts,id',
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('chart_of_accounts')->whereNull('deleted_at'),
                ],
                'account_type' => 'nullable|string|max:255',
                'visibility' => 'required|in:public,private', // ✅ NEW VALIDATION
                'receivables' => 'required|numeric',
                'payables' => 'required|numeric',
                'credit_limit' => 'required|numeric',
                'opening_date' => 'required|date',
                'remarks' => 'nullable|string|max:800',
                'address' => 'nullable|string|max:250',
                'phone_no' => 'nullable|string|max:250',
            ]);

            $subHead = SubHeadOfAccounts::findOrFail($request->shoa_id);
            $hoaCode = $subHead->hoa_id;
            $shoaCode = str_pad($subHead->id, 2, '0', STR_PAD_LEFT);
            $prefix = $hoaCode . $shoaCode;

            // ✅ Include soft-deleted records
            $existingCodes = ChartOfAccounts::withTrashed()
                ->where('account_code', 'like', $prefix . '%')
                ->pluck('account_code')
                ->map(function ($code) use ($prefix) {
                    return intval(substr($code, strlen($prefix)));
                })
                ->sort()
                ->values();

            $nextNumber = $existingCodes->last() + 1;
            $coaCode = str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
            $accountCode = $prefix . $coaCode;

            Log::info('Generated Unique Account Code', ['account_code' => $accountCode]);

            $account = ChartOfAccounts::create([
                'shoa_id' => $request->shoa_id,
                'account_code' => $accountCode,
                'name' => $request->name,
                'account_type' => $request->account_type,
                'visibility' => $request->visibility, // ✅ NEW FIELD
                'receivables' => $request->receivables,
                'payables' => $request->payables,
                'credit_limit' => $request->credit_limit,
                'opening_date' => $request->opening_date,
                'remarks' => $request->remarks,
                'address' => $request->address,
                'phone_no' => $request->phone_no,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            Log::info('Account Successfully Created', ['account' => $account]);

            return redirect()->route('coa.index')->with('success', 'Chart of Account created successfully.');

        } catch (\Exception $e) {
            Log::error('Error in COA Store', [
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            return redirect()->back()->with('error', 'Something went wrong. Please check logs.');
        }
    }

    public function edit($id)
    {
        $account = ChartOfAccounts::findOrFail($id);
        return response()->json($account);
    }

    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'shoa_id' => 'required|exists:sub_head_of_accounts,id',
                'name' => 'required|string|max:255|unique:chart_of_accounts,name,' . $id,
                'account_type' => 'nullable|string|max:255',
                'visibility' => 'required|in:public,private', // ✅ NEW VALIDATION
                'receivables' => 'required|numeric',
                'payables' => 'required|numeric',
                'opening_date' => 'required|date',
                'credit_limit' => 'required|numeric',
                'remarks' => 'nullable|string|max:800',
                'address' => 'nullable|string|max:250',
                'phone_no' => 'nullable|string|max:250',
            ]);

            $account = ChartOfAccounts::findOrFail($id);

            // ✅ Update fields safely
            $account->update([
                'shoa_id'      => $request->shoa_id,
                'name'         => $request->name,
                'account_type' => $request->account_type,
                'visibility'   => $request->visibility, // ✅ NEW FIELD
                'receivables'  => $request->receivables,
                'payables'     => $request->payables,
                'credit_limit' => $request->credit_limit,
                'opening_date' => $request->opening_date,
                'remarks'      => $request->remarks,
                'address'      => $request->address,
                'phone_no'     => $request->phone_no,
                'updated_by'   => auth()->id(), // ✅ log who updated
            ]);

            return redirect()->route('coa.index')->with('success', 'Chart of Account updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function show($id)
    {
        $account = ChartOfAccounts::with('subHeadOfAccount')->findOrFail($id);
        return response()->json($account);
    }

    public function destroy($id)
    {
        try {
            $chartOfAccount = ChartOfAccounts::findOrFail($id);
            
            // Optional: Check if the account has linked transactions before deleting
            // if ($chartOfAccount->transactions()->exists()) {
            //     return redirect()->back()->with('error', 'Cannot delete account with existing transactions.');
            // }

            $chartOfAccount->delete();

            return redirect()->route('coa.index')->with('success', 'Chart of Account deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error deleting account: ' . $e->getMessage());
        }
    }
}
