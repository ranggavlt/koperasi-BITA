@props([
    'striped' => true
])

<div class="flex flex-col">

    <div class="overflow-x-auto">

        <table class="items-center w-full mb-0 align-top border-gray-200 text-slate-500">

            {{-- TABLE HEAD --}}
            <thead class="align-bottom bg-gray-50">

                {{ $head }}

            </thead>


            {{-- TABLE BODY --}}
            <tbody>

                {{ $slot }}

            </tbody>

        </table>

    </div>

</div>

<style>

table th {
    padding: 12px 16px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748b;
    border-bottom: 1px solid #e5e7eb;
}

table td {
    padding: 12px 16px;
    font-size: 13px;
    border-bottom: 1px solid #e5e7eb;
}

tbody tr:hover {
    background-color: #f8fafc;
}

</style>
