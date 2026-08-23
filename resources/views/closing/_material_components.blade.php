@php
    $totalPembelian = $materialRows->sum('purchase_value');
@endphp
<div class="mt-6 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
    <div class="border-b border-slate-200 p-5 dark:border-slate-700">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-bold text-slate-800 dark:text-white">Rincian Material</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Qty terisi otomatis dari Expense kategori Bahan Baku. Stock Akhir isi manual (stok fisik akhir bulan).</p>
            </div>
            <div class="text-sm text-slate-500 dark:text-slate-400">Total Pembelian: <span class="font-semibold text-slate-800 dark:text-white">Rp{{ number_format($totalPembelian,0,',','.') }}</span></div>
        </div>
    </div>

    @if(!$closing?->is_locked && $closing)
    <form method="POST" action="{{ route('closing.update-stock-akhir') }}" id="form-stock-akhir">
        @csrf
        <input type="hidden" name="month" value="{{ $closing->month }}">
        <input type="hidden" name="year" value="{{ $closing->year }}">
    @endif

    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-left dark:border-slate-700 dark:bg-slate-900">
            <tr>
                <th class="p-3 font-semibold text-slate-600">Nama komponen</th>
                <th class="p-3 text-right font-semibold text-slate-600">Stock Awal</th>
                <th class="p-3 text-right font-semibold text-slate-600">Harga Komponen</th>
                <th class="p-3 text-right font-semibold text-slate-600">Qty</th>
                <th class="p-3 text-right font-semibold text-slate-600">Pembelian</th>
                <th class="p-3 text-right font-semibold text-slate-600">Stock akhir</th>
            </tr>
        </thead>
        <tbody>
            @forelse($materialRows as $row)
                <tr class="border-b border-slate-100 hover:bg-slate-50/50 dark:border-slate-700 dark:hover:bg-slate-900/50">
                    <td class="p-3 font-medium text-slate-800 dark:text-white">{{ $row['material']->name }} <span class="text-xs text-slate-500 dark:text-slate-400">{{ $row['material']->unit }}</span></td>
                    <td class="p-3 text-right text-slate-800 dark:text-white">{{ number_format($row['stock_awal'], 2, ',', '.') }}</td>
                    <td class="p-3 text-right font-medium text-slate-800 dark:text-white">Rp{{ number_format($row['unit_cost'], 0, ',', '.') }}</td>
                    <td class="p-3 text-right text-slate-800 dark:text-white">{{ $row['incoming_quantity'] > 0 ? number_format($row['incoming_quantity'], 2, ',', '.') : '' }}</td>
                    <td class="p-3 text-right font-semibold text-slate-800 dark:text-white">{{ $row['purchase_value'] > 0 ? 'Rp' . number_format($row['purchase_value'], 0, ',', '.') : '' }}</td>
                    <td class="p-3 text-right">
                        @if($closing && !$closing->is_locked)
                            <input type="number" step="0.01" min="0"
                                name="stock_akhir[{{ $row['id'] ?? $row['material']->id }}]"
                                value="{{ number_format($row['stock_akhir'], 2, '.', '') }}"
                                class="w-24 rounded-lg border border-slate-300 px-2 py-1 text-right text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                                form="form-stock-akhir">
                        @else
                            <span class="font-medium text-slate-800">{{ number_format($row['stock_akhir'], 2, ',', '.') }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="p-8 text-center text-slate-400 dark:text-slate-400">Belum ada data material. Input Expense kategori Bahan Baku terlebih dahulu.</td>
                </tr>
            @endforelse

            {{-- Baris fixed costs (disatukan di sini) --}}
            @if(isset($closing))
                @php
                    $fixedRows = [
                        ['nama' => 'Gaji Karyawan', 'nilai' => (float)($closing->gaji_karyawan ?? 0)],
                        
                    ];
                    if ((float)($closing->operasional ?? 0) > 0) {
                        $fixedRows[] = ['nama' => 'Operasional', 'nilai' => (float)($closing->operasional)];
                    }
                    if ((float)($closing->teknisi_mesin ?? 0) > 0) {
                        $fixedRows[] = ['nama' => 'Teknisi Mesin', 'nilai' => (float)($closing->teknisi_mesin)];
                    }
                @endphp
                @foreach($fixedRows as $fr)
                    @if($fr['nilai'] > 0)
                        <tr class="border-b border-slate-100 bg-amber-50/50 dark:border-slate-700">
                            <td class="p-3 font-medium text-slate-700 dark:text-white">{{ $fr['nama'] }}</td>
                            <td class="p-3"></td>
                            <td class="p-3"></td>
                            <td class="p-3"></td>
                            <td class="p-3 text-right font-semibold text-slate-800 dark:text-white">Rp{{ number_format($fr['nilai'], 0, ',', '.') }}</td>
                            <td class="p-3"></td>
                        </tr>
                    @endif
                @endforeach
            @endif
        </tbody>
        @if($materialRows->isNotEmpty())
            @php
                $totalAll = $totalPembelian
                    + (float)($closing->gaji_karyawan ?? 0)
                    
                    + (float)($closing->operasional ?? 0)
                    + (float)($closing->teknisi_mesin ?? 0);
            @endphp
            <tfoot>
                <tr class="border-t-2 border-slate-800 bg-slate-50 dark:border-slate-700 dark:bg-slate-900">
                    <td class="p-3 font-bold text-slate-800 dark:text-white">Total</td>
                    <td class="p-3"></td>
                    <td class="p-3"></td>
                    <td class="p-3"></td>
                    <td class="p-3 text-right font-bold text-slate-800 dark:text-white">Rp{{ number_format($totalAll, 0, ',', '.') }}</td>
                    <td class="p-3"></td>
                </tr>
            </tfoot>
        @endif
    </table>
    </div>

    @if(!$closing?->is_locked && $closing)
        <div class="border-t border-slate-200 px-5 py-4">
            <button type="submit" form="form-stock-akhir"
                    class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-700 transition">
                Simpan Stock Akhir
            </button>
        </div>
    @endif

    @if(!$closing?->is_locked && $closing)
    </form>
    @endif
</div>
