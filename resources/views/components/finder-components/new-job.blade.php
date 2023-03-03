@props(['$musicianNumber'])

<div class="overflow-hidden shadow sm:rounded-md more-job-template">
    <div class="bg-white px-4 py-5 sm:p-6">
        <div class="grid grid-cols-6 gap-6">
            <div class="col-span-6 sm:col-span-2 lg:col-span-1">
                <label for="musician_name" class="block text-sm font-medium text-gray-700">
                    Musician #{{ $musicianNumber }}
                </label>
                <select name="musicians[{{ $musicianNumber }}]['fill_status']" id="musician_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <option value="unfilled">Unfilled</option>
                    <option value="filled">Already filled</option>
                </select>
            </div>
            <div class="col-span-3 sm:col-span-2 lg:col-span-1">
                <label for="instrument" class="block text-sm font-medium text-gray-700">
                    Instrument(s)
                </label>
                <select name="musicians[{{ $musicianNumber }}]['instruments']" id="cars" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @foreach(config('gigs.instruments') as $instrument)
                        <option value="{{ $instrument }}">{{ $instrument }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-span-3 sm:col-span-2 lg:col-span-1">
                <label for="payment" class="block text-sm font-medium text-gray-700">
                    Payment
                </label>
                <input id="payment-all" name="musicians[{{ $musicianNumber }}]['payment']" type="number" min="0" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            </div>
            <div class="col-span-6 lg:col-span-3">
                <label for="extra_details" class="block text-sm font-medium text-gray-700">Extra Details</label>
                <input type="text" name="musicians[{{ $musicianNumber }}]['extra_details']" id="extra_details" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            </div>
        </div>
    </div>
</div>