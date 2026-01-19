@extends('layouts.app')

@section('title', 'Accounting Reports')

@php
    $activeTab = request('tab', 'general_ledger');
    $reportList = [
        'general_ledger'   => 'General Ledger',
        'trial_balance'    => 'Trial Balance',
        'profit_loss'      => 'Profit & Loss',
        'balance_sheet'    => 'Balance Sheet',
        'party_ledger'     => 'Party Ledger',
        'receivables'      => 'Receivables',
        'payables'         => 'Payables',
        'cash_book'        => 'Cash Book',
        'bank_book'        => 'Bank Book',
        'journal_book'     => 'Journal / Day Book',
        'expense_analysis' => 'Expense Analysis',
        'cash_flow'        => 'Cash Flow',
    ];
@endphp

@section('content')
<div class="card">
    <div class="card-body">
        <div class="tabs">
            {{-- ================= TABS ================= --}}
            <ul class="nav nav-tabs mb-3" role="tablist">
                @foreach ($reportList as $key => $label)
                    <li class="nav-item">
                        <a class="nav-link" 
                        data-bs-toggle="tab" 
                        href="#{{ $key }}" 
                        role="tab">
                            {{ $label }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
        {{-- ================= TAB CONTENT ================= --}}
        <div class="tab-content">
            @foreach ($reportList as $key => $label)
            <div class="tab-pane fade {{ $activeTab === $key ? 'show active' : '' }}" 
                 id="{{ $key }}" 
                 role="tabpanel">

                {{-- ================= FILTER FORM ================= --}}
                <form method="GET" action="{{ route('reports.accounts') }}" class="row g-2 mb-4 p-3 bg-light border rounded">
                    <input type="hidden" name="tab" value="{{ $key }}">

                    <div class="col-md-3">
                        <label class="small fw-bold">From Date</label>
                        <input type="date" name="from_date" value="{{ request('from_date', $from) }}" class="form-control" required>
                    </div>

                    <div class="col-md-3">
                        <label class="small fw-bold">To Date</label>
                        <input type="date" name="to_date" value="{{ request('to_date', $to) }}" class="form-control" required>
                    </div>

                    <div class="col-md-4">
                        <label class="small fw-bold">Select Account (For Ledgers)</label>
                        <select name="account_id" class="form-control select2">
                            <option value="">-- All Accounts --</option>
                            @foreach ($chartOfAccounts as $coa)
                                <option value="{{ $coa->id }}" {{ request('account_id') == $coa->id ? 'selected' : '' }}>
                                    {{ $coa->code }} - {{ $coa->name }} ({{ $coa->account_type }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter"></i> Generate
                        </button>
                    </div>
                </form>

                {{-- ================= REPORT TABLE ================= --}}
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-primary">
                            @if (in_array($key, ['general_ledger', 'party_ledger', 'cash_book', 'bank_book']))
                                <tr>
                                    <th>Date</th>
                                    <th>{{ $key === 'party_ledger' ? 'Party Name' : 'Account Name' }}</th>
                                    <th>Reference / Voucher</th>
                                    <th class="text-end">Debit</th>
                                    <th class="text-end">Credit</th>
                                    @if (!in_array($key, ['cash_book', 'bank_book']))
                                        <th class="text-end">Running Balance</th>
                                    @endif
                                </tr>
                            @elseif ($key === 'trial_balance')
                                <tr>
                                    <th>Account Name</th>
                                    <th>Type</th>
                                    <th class="text-end">Debit Balance</th>
                                    <th class="text-end">Credit Balance</th>
                                </tr>
                            @elseif (in_array($key, ['profit_loss', 'expense_analysis', 'cash_flow', 'receivables', 'payables']))
                                <tr>
                                    <th>Particulars / Name</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            @elseif ($key === 'balance_sheet')
                                <tr>
                                    <th width="35%">Assets</th>
                                    <th width="15%" class="text-end">Amount</th>
                                    <th width="35%">Liabilities & Equity</th>
                                    <th width="15%" class="text-end">Amount</th>
                                </tr>
                            @elseif ($key === 'journal_book')
                                <tr>
                                    <th>Date</th>
                                    <th>Debit Account</th>
                                    <th>Credit Account</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            @endif
                        </thead>

                        <tbody>
                            @php $footerTotal = 0; @endphp

                            @forelse ($reports[$key] ?? [] as $row)
                                @php
                                    // Check if row is a header or total line (especially for P&L)
                                    $firstCol = (string)($row[0] ?? '');
                                    $isSpecialRow = in_array($firstCol, ['REVENUE', 'EXPENSES', 'NET PROFIT/LOSS', 'Opening Balance']);
                                    $rowClass = $isSpecialRow ? 'table-secondary fw-bold' : '';
                                @endphp

                                <tr class="{{ $rowClass }}">
                                    @foreach ($row as $index => $col)
                                        @php
                                            // Cleaning logic: remove commas to check if numeric
                                            $rawString = str_replace(',', '', (string)$col);
                                            $isNumeric = is_numeric($rawString) && $col !== '';
                                            
                                            // Running total for specific summary reports (usually 2nd column)
                                            if (in_array($key, ['receivables', 'payables', 'expense_analysis']) && $index === 1 && !$isSpecialRow) {
                                                $footerTotal += (float)$rawString;
                                            }
                                        @endphp

                                        <td class="{{ $isNumeric ? 'text-end' : '' }}">
                                            @if($isNumeric)
                                                {{-- Check if already formatted (contains comma), if not, format it --}}
                                                {{ strpos((string)$col, ',') !== false ? $col : number_format((float)$col, 2) }}
                                            @else
                                                {{ $col }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-5">
                                        No data found for the selected criteria.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>

                        {{-- ================= SUMMARY FOOTER ================= --}}
                        @if (in_array($key, ['receivables', 'payables', 'expense_analysis']) && count($reports[$key] ?? []) > 0)
                            <tfoot class="table-primary">
                                <tr>
                                    <th class="text-uppercase">Total {{ str_replace('_', ' ', $key) }}</th>
                                    <th class="text-end">{{ number_format($footerTotal, 2) }}</th>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endsection