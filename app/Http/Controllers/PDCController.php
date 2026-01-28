<?php

namespace App\Http\Controllers;

use App\Models\PostDatedCheque;
use App\Models\ChartOfAccounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class PDCController extends Controller
{
    public function index() {
        $cheques = PostDatedCheque::with('chartOfAccount')->get();
        // Fetch all COA for the dropdown
        $chartOfAccounts = ChartOfAccounts::orderBy('name', 'asc')->get(); 
        
        return view('pdc.index', compact('cheques', 'chartOfAccounts'));
    }

    public function create()
    {
        return view('admin.pdc.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cheque_number' => 'required|unique:post_dated_cheques',
            'cheque_date'   => 'required|date',
            'amount'        => 'required|numeric',
            'bank_name'     => 'required|string',
            'coa_id'        => 'required|exists:chart_of_accounts,id', // Validate against COA table
        ]);

        $validated['created_by'] = Auth::id();
        
        PostDatedCheque::create($validated);

        return redirect()->route('pdc.index')->with('success', 'Cheque recorded successfully.');
    }

    // Add an edit method to serve JSON for your modal
    public function edit($id)
    {
        $cheque = PostDatedCheque::findOrFail($id);
        return response()->json($cheque);
    }

    // Add an update method for the Edit Modal
    public function update(Request $request, $id)
    {
        $cheque = PostDatedCheque::findOrFail($id);
        $validated = $request->validate([
            'cheque_number' => 'required|unique:post_dated_cheques,cheque_number,'.$id,
            'cheque_date'   => 'required|date',
            'amount'        => 'required|numeric',
            'bank_name'     => 'required|string',
            'coa_id'        => 'required|exists:chart_of_accounts,id',
        ]);

        $cheque->update($validated);
        return redirect()->route('pdc.index')->with('success', 'Cheque updated.');
    }

    public function deposit($id)
    {
        $cheque = PostDatedCheque::findOrFail($id);
        $cheque->update(['status' => 'deposited', 'deposited_at' => now()]);
        return back()->with('success', 'Cheque marked as deposited.');
    }

    public function clear($id)
    {
        $cheque = PostDatedCheque::findOrFail($id);
        $cheque->update(['status' => 'cleared', 'cleared_at' => now()]);
        // Trigger accounting entry here if needed
        return back()->with('success', 'Cheque cleared.');
    }

    public function bounce(Request $request, $id)
    {
        $cheque = PostDatedCheque::findOrFail($id);
        $cheque->update(['status' => 'bounced', 'remarks' => $request->remarks]);
        return back()->with('warning', 'Cheque marked as bounced.');
    }

    public function destroy($id)
    {
        PostDatedCheque::findOrFail($id)->delete();
        return back()->with('success', 'Record deleted.');
    }
}