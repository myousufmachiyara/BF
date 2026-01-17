@extends('layouts.app')

@section('title', 'Accounting Reports')

@php
    $activeTab = request('tab', 'general_ledger');
@endphp

@section('content')
<div class="card">
    <div class="card-body">
        <div class="tabs">
            {{-- ================= TABS ================= --}}
            <ul class="nav nav-tabs mb-3" role="tablist">
                @foreach ([
                    'general_ledger' => 'General Ledger',
                    'trial_balance'  => 'Trial Balance',
                    'profit_loss'    => 'Profit & Loss',
                    'balance_sheet'  => 'Balance Sheet',
                    'party_ledger'   => 'Party Ledger',
                    'receivables'    => 'Receivables',
                    'payables'       => 'Payables',
                    'cash_book'      => 'Cash Book',
                    'bank_book'      => 'Bank Book',
                    'journal_book'   => 'Journal / Day Book',
                    'expense_analysis'=> 'Expense Analysis',
                    'cash_flow'      => 'Cash Flow',
                ] as $key => $label)
                    <li class="nav-item">
                        <a class="nav-link {{ $activeTab === $key ? 'active' : '' }}"
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
            @foreach ([
                'general_ledger' => 'General Ledger',
                'trial_balance'  => 'Trial Balance',
                'profit_loss'    => 'Profit & Loss',
                'balance_sheet'  => 'Balance Sheet',
                'party_ledger'   => 'Party Ledger',
                'receivables'    => 'Receivables',
                'payables'       => 'Payables',
                'cash_book'      => 'Cash Book',
                'bank_book'      => 'Bank Book',
                'journal_book'   => 'Journal / Day Book',
                'expense_analysis'=> 'Expense Analysis',
                'cash_flow'      => 'Cash Flow',
            ] as $key => $label)

            <div class="tab-pane fade {{ $activeTab === $key ? 'show active' : '' }}"
                 id="{{ $key }}"
                 role="tabpanel">

                {{-- ================= FILTER ================= --}}
                <form method="GET" action="{{ route('reports.accounts') }}" class="row g-2 mb-3">
                    <input type="hidden" name="tab" value="{{ $key }}">

                    <div class="col-md-3">
                        <input type="date" name="from_date"
                               value="{{ request('from_date', $from) }}"
                               class="form-control" required>
                    </div>

                    <div class="col-md-3">
                        <input type="date" name="to_date"
                               value="{{ request('to_date', $to) }}"
                               class="form-control" required>
                    </div>

                    <div class="col-md-4">
                        <select name="account_id" class="form-control select2">
                            <option value="">-- All Accounts --</option>
                            @foreach ($chartOfAccounts as $coa)
                                <option value="{{ $coa->id }}"
                                    {{ request('account_id') == $coa->id ? 'selected' : '' }}>
                                    {{ $coa->code }} - {{ $coa->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <button class="btn btn-primary w-100">Filter</button>
                    </div>
                </form>

                {{-- ================= TABLE ================= --}}
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead class="table-light">
                            @if ($key === 'general_ledger' || $key === 'party_ledger' || $key === 'cash_book' || $key === 'bank_book')
                                <tr>
                                    <th>Date</th>
                                    <th>{{ $key === 'party_ledger' ? 'Party' : 'Account' }}</th>
                                    <th>Voucher</th>
                                    <th class="text-end">Debit</th>
                                    <th class="text-end">Credit</th>
                                    @if ($key !== 'cash_book' && $key !== 'bank_book')
                                        <th class="text-end">Balance</th>
                                    @endif
                                </tr>
                            @elseif ($key === 'trial_balance')
                                <tr>
                                    <th>Account</th>
                                    <th class="text-end">Debit</th>
                                    <th class="text-end">Credit</th>
                                </tr>
                            @elseif ($key === 'profit_loss' || $key === 'expense_analysis' || $key === 'cash_flow')
                                <tr>
                                    <th>Particulars</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            @elseif ($key === 'balance_sheet')
                                <tr>
                                    <th>Assets</th>
                                    <th>Amount</th>
                                    <th>Liabilities</th>
                                    <th>Amount</th>
                                </tr>
                            @elseif ($key === 'receivables' || $key === 'payables')
                                <tr>
                                    <th>Name</th>
                                    <th class="text-end">Amount</th>
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
                            @php $totalAmount = 0; @endphp {{-- INITIALIZE HERE --}}
                            @forelse ($reports[$key] ?? [] as $row)
                                <tr>
                                    @foreach ($row as $col)
                                        <td class="{{ is_numeric(str_replace(',', '', $col)) ? 'text-end' : '' }}">
                                            {{ is_numeric($col) ? number_format($col, 2) : $col }}
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center text-muted">No data found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        {{-- ================= TOTAL FOOTER ================= --}}
                        @if (($key === 'receivables' || $key === 'payables') && count($reports[$key] ?? []) > 0)
                            <tfoot class="table-dark">
                                <tr>
                                    <th>TOTAL</th>
                                    <th class="text-end">{{ number_format($totalAmount, 2) }}</th>
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
