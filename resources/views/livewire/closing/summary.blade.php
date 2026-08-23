<div>
    <div class="bg-white rounded-lg shadow p-6 dark:bg-slate-800 dark:text-slate-200">
        <h3 class="text-lg font-medium text-gray-900 mb-4 dark:text-slate-100">Ringkasan Closing</h3>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                <p class="text-sm text-green-600 font-medium">Total Pemasukan</p>
                <p class="text-2xl font-bold text-green-700">Rp {{ number_format($totalIncome, 0, ',', '.') }}</p>
            </div>
            <div class="bg-red-50 p-4 rounded-lg border border-red-200">
                <p class="text-sm text-red-600 font-medium">Total Pengeluaran</p>
                <p class="text-2xl font-bold text-red-700">Rp {{ number_format($totalExpense, 0, ',', '.') }}</p>
            </div>
            <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                <p class="text-sm text-blue-600 font-medium">Laba/Rugi Bersih</p>
                <p class="text-2xl font-bold {{ $profit >= 0 ? 'text-blue-700' : 'text-red-700' }}">Rp {{ number_format($profit, 0, ',', '.') }}</p>
            </div>
        </div>

        @if($closing)
        <div class="mb-6 p-4 bg-yellow-50 rounded-lg border border-yellow-200">
            <p class="text-sm text-yellow-700">Closing sudah digenerate untuk periode ini.</p>
            <p class="text-sm text-yellow-600">HPP: Rp {{ number_format($closing->hpp, 0, ',', '.') }} | Sisa Material: Rp {{ number_format($closing->remaining_material, 0, ',', '.') }}</p>
        </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <h4 class="text-sm font-medium text-gray-700 mb-2">Pemasukan per Pelanggan (Top 10)</h4>
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-1">Pelanggan</th>
                            <th class="text-right py-1">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($incomeByCustomer as $customer => $total)
                        <tr class="border-b border-gray-100">
                            <td class="py-1">{{ $customer }}</td>
                            <td class="text-right py-1">Rp {{ number_format($total, 0, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="2" class="text-center text-gray-500 py-2">Belum ada data</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div>
                <h4 class="text-sm font-medium text-gray-700 mb-2">Pengeluaran per Kategori (Top 10)</h4>
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-1">Kategori</th>
                            <th class="text-right py-1">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($expenseByCategory as $category => $total)
                        <tr class="border-b border-gray-100">
                            <td class="py-1">{{ $category }}</td>
                            <td class="text-right py-1">Rp {{ number_format($total, 0, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="2" class="text-center text-gray-500 py-2">Belum ada data</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex justify-end">
            <button wire:click="generateClosing" class="branch-required inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                Generate Closing
            </button>
        </div>
    </div>
</div>