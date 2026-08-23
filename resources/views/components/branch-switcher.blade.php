@if(auth()->check())
    @php
        $canAll = auth()->user()->hasAnyRole(['Super Admin','Owner']);
        $current = $globalBranches->first() ?? null;
    @endphp
    <div class="hidden md:block">
        @if($canAll)
            <form method="GET" action="{{ url()->current() }}" class="">
                <label class="block">
                    <span class="sr-only">Cabang</span>
                    <select name="branch_id" onchange="this.form.submit()" class="mt-0 w-48 rounded-xl border border-[#E2E8F0] bg-white px-3 py-2 text-sm text-slate-800 outline-none transition focus:border-[#2563EB] focus:ring-2 focus:ring-[#2563EB]/20 dark:border-[#253247] dark:bg-[#111827] dark:text-slate-100 dark:placeholder-slate-500">
                        <option value="0" @selected((int)$globalSelectedBranchId === 0)>Semua Cabang</option>
                        @foreach($globalBranches as $b)
                            <option value="{{ $b->id }}" @selected((int)$globalSelectedBranchId === $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </label>
                <input type="hidden" name="month" value="{{ request('month') }}">
            </form>
        @else
            <div class="inline-flex items-center gap-2 rounded-xl border border-[#E2E8F0] bg-white px-3 py-2 text-sm text-slate-800 dark:border-[#253247] dark:bg-[#111827] dark:text-slate-100">
                <x-heroicon-o-map class="h-4 w-4 text-slate-400"/>
                <span class="font-medium">{{ $current?->name ?? 'Cabang' }}</span>
            </div>
        @endif
    </div>
@endif