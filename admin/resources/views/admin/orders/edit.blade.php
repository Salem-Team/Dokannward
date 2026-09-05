@extends('admin.layouts.app')

@section('title', 'Edit Order')

@section('content')
    <div class="brand-studio-page">
        <div class="brand-studio-page__header">
            <div>
                <p class="brand-studio-page__eyebrow">Commerce · Orders</p>
                <h1 class="brand-studio-page__title">Edit {{ $order->order_number }}</h1>
                <p class="brand-studio-page__sub">
                    Update status, tracking, and notes. Status changes write to the timeline and restock inventory on cancel.
                </p>
            </div>
            <x-admin.button variant="secondary" icon="fas fa-arrow-left" size="sm"
                onclick="window.location='{{ route('admin.orders.show', $order->id) }}'">
                Back to order
            </x-admin.button>
        </div>
<form action="{{ route('admin.orders.update', $order->id) }}" method="POST" class="brand-studio brand-studio--full">
            @csrf
            @method('PUT')

            <div class="brand-studio__grid brand-studio__grid--wide">
                <div class="brand-studio__editor space-y-8">
                    <section class="brand-studio__section">
                        <p class="brand-studio__eyebrow">Fulfillment</p>
                        <h2 class="brand-studio__section-title">Status & tracking</h2>

                        <div class="mt-5 space-y-5">
                            <div>
                                <label class="brand-studio__label" for="status">Order status</label>
                                <select id="status" name="status"
                                    class="brand-studio__input @error('status') brand-studio__input--error @enderror" required>
                                    @foreach (\App\Services\OrderLifecycle::STATUSES as $status)
                                        <option value="{{ $status }}" @selected(old('status', $order->status) === $status)>
                                            {{ ucfirst($status) }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('status')
                                    <p class="brand-studio__error">{{ $message }}</p>
                                @enderror
                                <p class="brand-studio__hint mt-1.5">
                                    Cancelling restores stock for line items. Shipped / delivered stamp the timeline.
                                </p>
                            </div>

                            <div>
                                <label class="brand-studio__label" for="delivery_tracking_number">Tracking number</label>
                                <input id="delivery_tracking_number" name="delivery_tracking_number" type="text"
                                    value="{{ old('delivery_tracking_number', $order->delivery_tracking_number) }}"
                                    placeholder="Courier tracking ID"
                                    class="brand-studio__input @error('delivery_tracking_number') brand-studio__input--error @enderror">
                                @error('delivery_tracking_number')
                                    <p class="brand-studio__error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="studio-visibility">
                                <input type="hidden" name="restock" value="0">
                                <label class="studio-visibility__switch" for="restock">
                                    <input type="checkbox" id="restock" name="restock" value="1"
                                        class="sr-only peer" @checked(old('restock', '1') !== '0')>
                                    <span class="studio-visibility__track" aria-hidden="true">
                                        <span class="studio-visibility__thumb"></span>
                                    </span>
                                    <span class="studio-visibility__copy">
                                        <span class="studio-visibility__state">Restock on cancel</span>
                                        <span class="studio-visibility__hint">When status becomes cancelled, return units to inventory.</span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </section>

                    <section class="brand-studio__section">
                        <p class="brand-studio__eyebrow">Desk notes</p>
                        <h2 class="brand-studio__section-title">Internal / client notes</h2>
                        <div class="mt-5">
                            <label class="brand-studio__label" for="notes">Notes</label>
                            <textarea id="notes" name="notes" rows="5"
                                placeholder="WhatsApp follow-up, delivery instructions, admin remarks…"
                                class="brand-studio__input brand-studio__textarea @error('notes') brand-studio__input--error @enderror">{{ old('notes', $order->notes) }}</textarea>
                            @error('notes')
                                <p class="brand-studio__error">{{ $message }}</p>
                            @enderror
                        </div>
                    </section>

                    <div class="brand-studio__actions">
                        <button type="button" class="brand-studio__btn brand-studio__btn--ghost"
                            onclick="window.location='{{ route('admin.orders.show', $order->id) }}'">
                            Cancel
                        </button>
                        <button type="submit" class="brand-studio__btn brand-studio__btn--ink">
                            <i class="fas fa-check"></i>
                            Save order
                        </button>
                    </div>
                </div>

                <aside class="brand-studio__preview-col space-y-5">
                    <section class="brand-studio__section">
                        <p class="brand-studio__eyebrow">Summary</p>
                        <h2 class="brand-studio__section-title mb-4">{{ $order->order_number }}</h2>
                        <dl class="space-y-3 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt class="text-zibra-ash">Client</dt>
                                <dd class="font-medium text-zibra-ink dark:text-white text-right">{{ $order->customerName() }}</dd>
                            </div>
                            @if ($order->customerPhone())
                                <div class="flex justify-between gap-3">
                                    <dt class="text-zibra-ash">Phone</dt>
                                    <dd class="tabular-nums text-zibra-ink dark:text-white">{{ $order->customerPhone() }}</dd>
                                </div>
                            @endif
                            <div class="flex justify-between gap-3">
                                <dt class="text-zibra-ash">Placed</dt>
                                <dd class="text-zibra-ink dark:text-white">{{ ($order->placed_at ?? $order->created_at)->format('M d, Y H:i') }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-zibra-ash">Total</dt>
                                <dd class="font-semibold text-zibra-ink dark:text-white tabular-nums">
                                    {{ \App\Support\Money::format($order->total_amount) }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-zibra-ash">Lines</dt>
                                <dd class="text-zibra-ink dark:text-white tabular-nums">{{ $order->items->count() }}</dd>
                            </div>
                        </dl>
                    </section>

                    @if ($order->statusHistories->isNotEmpty())
                        <section class="brand-studio__section">
                            <p class="brand-studio__eyebrow">Audit</p>
                            <h2 class="brand-studio__section-title mb-4">Recent changes</h2>
                            <ol class="space-y-3">
                                @foreach ($order->statusHistories->take(6) as $entry)
                                    <li class="text-sm border-b border-zibra-line dark:border-gray-700 pb-3 last:border-0">
                                        <p class="font-medium text-zibra-ink dark:text-white">{{ ucfirst($entry->status) }}</p>
                                        <p class="text-xs text-zibra-ash mt-0.5">
                                            {{ $entry->created_at?->format('M d · g:i A') }}
                                            @if ($entry->changedBy)
                                                · {{ $entry->changedBy->name }}
                                            @endif
                                        </p>
                                        @if ($entry->note)
                                            <p class="text-xs text-zibra-ash mt-1">{{ $entry->note }}</p>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        </section>
                    @endif
                </aside>
            </div>
        </form>
    </div>
@endsection
