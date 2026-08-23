<div>
    <div class="bg-white rounded-lg shadow p-6 dark:bg-slate-800 dark:text-slate-200">
        <h3 class="text-lg font-medium text-gray-900 mb-4 dark:text-slate-100">Input Pendapatan (Pemasukan)</h3>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-slate-200">Paste dari Excel</label>
            <textarea wire:model="pasteData" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" rows="3" placeholder="Copy dari Excel dan paste di sini..."></textarea>
            <button wire:click="pasteRows" class="branch-required mt-2 inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                Parse Data
            </button>
        </div>

        <div class="overflow-x-auto mb-4">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left">Tanggal</th>
                        <th class="px-3 py-2 text-left">Pelanggan</th>
                        <th class="px-3 py-2 text-left">Produk</th>
                        <th class="px-3 py-2 text-right">Qty</th>
                        <th class="px-3 py-2 text-right">Harga</th>
                        <th class="px-3 py-2 text-right">Total</th>
                        <th class="px-3 py-2 text-left">Ket</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 dark:bg-slate-800 dark:divide-slate-700">
                    @foreach($rows as $index => $row)
                    <tr>
                        <td class="px-3 py-1">
                            <input type="date" wire:model="rows.{{ $index }}.date" class="w-full border-gray-300 rounded text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                            @error("rows.{{ $index }}.date") <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </td>
                        <td class="px-3 py-1">
                            <input type="text" wire:model="rows.{{ $index }}.customer" class="w-full border-gray-300 rounded text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100" placeholder="Pelanggan">
                            @error("rows.{{ $index }}.customer") <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </td>
                        <td class="px-3 py-1">
                            <input type="text" wire:model="rows.{{ $index }}.product" class="w-full border-gray-300 rounded text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100" placeholder="Produk">
                            @error("rows.{{ $index }}.product") <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </td>
                        <td class="px-3 py-1">
                            <input type="number" step="0.01" wire:model="rows.{{ $index }}.qty" class="w-20 border-gray-300 rounded text-sm text-right dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                            @error("rows.{{ $index }}.qty") <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </td>
                        <td class="px-3 py-1">
                            <input type="number" step="1" wire:model="rows.{{ $index }}.price" class="w-24 border-gray-300 rounded text-sm text-right dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                            @error("rows.{{ $index }}.price") <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </td>
                        <td class="px-3 py-1">
                            <input type="number" step="1" wire:model="rows.{{ $index }}.total" class="w-28 border-gray-300 rounded text-sm text-right font-medium dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                            @error("rows.{{ $index }}.total") <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </td>
                        <td class="px-3 py-1">
                            <input type="text" wire:model="rows.{{ $index }}.note" class="w-20 border-gray-300 rounded text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100" placeholder="Ket">
                        </td>
                        <td class="px-3 py-1">
                            <button wire:click="removeRow({{ $index }})" class="text-red-600 hover:text-red-900">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-between">
            <button wire:click="addRow" class="branch-required inline-flex items-center px-3 py-2 bg-gray-200 rounded-md text-sm text-gray-700 hover:bg-gray-300">
                + Tambah Baris
            </button>
            <button wire:click="save" class="branch-required inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700">
                Simpan Pendapatan
            </button>
        </div>
    </div>
</div>