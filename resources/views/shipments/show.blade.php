<x-app-layout>
<div class="container" style="padding-top: 2rem; max-width: 900px;">
    
    <div class="flex justify-between items-center" style="margin-bottom: 2rem;">
        <a href="{{ route('shipments.index') }}" style="display: inline-flex; align-items: center; gap: 0.5rem; color: var(--text-muted);">
            &larr; Back to Shipments
        </a>
        <span class="badge badge-blue" style="font-size: 0.9rem;">Tracking #{{ $shipment->tracking_number }}</span>
    </div>

    @if (session('success'))
        <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid var(--success); color: var(--success); padding: 1rem; border-radius: var(--radius-sm); margin-bottom: 2rem;">
            {{ session('success') }}
        </div>
    @endif

    <!-- Shipment Timeline Progress -->
    <div class="card" style="margin-bottom: 2rem; padding: 3rem 2rem;">
        <div style="display: flex; justify-content: space-between; position: relative;">
            
            @php
                $trackingStatuses = [
                    'order_placed' => ['icon' => '📦', 'label' => 'Order Placed'],
                    'picked_up' => ['icon' => '🚚', 'label' => 'Picked Up'],
                    'in_transit' => ['icon' => '✈️', 'label' => 'In Transit'],
                    'delivered' => ['icon' => '✅', 'label' => 'Delivered']
                ];
                
                // Note: database might have 'out_for_delivery' too. We can insert it if needed, 
                // but the prompt specified these 4 specific steps.
                
                $statusKeys = array_keys($trackingStatuses);
                
                // handle out_for_delivery edge case
                $currentStatusKey = $shipment->status;
                if($currentStatusKey === 'out_for_delivery') {
                    $currentStatusKey = 'in_transit'; // Treat it as past transit, before delivered. Or just use in_transit.
                }

                $currentIndex = array_search($currentStatusKey, $statusKeys);
                if($currentIndex === false) $currentIndex = 0; // fallback
            @endphp

            <div style="position: absolute; top: 1.25rem; left: 12%; right: 12%; height: 4px; background: var(--bg-main); border-radius: 2px; z-index: 1;">
                <div style="height: 100%; background: var(--accent-gradient); width: {{ ($currentIndex / (count($statusKeys) - 1)) * 100 }}%; transition: var(--transition); border-radius: 2px;"></div>
            </div>

            @foreach($trackingStatuses as $key => $data)
                @php
                    $stepIndex = array_search($key, $statusKeys);
                    $isActive = $stepIndex <= $currentIndex;
                    $isCurrent = $stepIndex === $currentIndex;
                @endphp
                <div class="flex-col items-center" style="position: relative; z-index: 2; width: 100px;">
                    <div style="width: 3rem; height: 3rem; border-radius: 50%; background: {{ $isActive ? 'var(--accent-primary)' : 'var(--bg-main)' }}; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; margin-bottom: 1rem; transition: var(--transition); box-shadow: {{ $isCurrent ? '0 0 0 6px rgba(139, 92, 246, 0.2)' : 'none' }}; border: 2px solid {{ $isActive ? 'transparent' : 'var(--border-color)' }};">
                        {{ $data['icon'] }}
                    </div>
                    <span style="font-size: 0.85rem; font-weight: {{ $isCurrent ? '600' : '500' }}; color: {{ $isActive ? 'var(--text-main)' : 'var(--text-muted)' }}; text-align: center;">{{ $data['label'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Details Grid -->
    <div class="grid grid-cols-2 gap-6" style="margin-bottom: 2rem;">
        
        <!-- Delivery Info -->
        <div class="card" style="display: flex; flex-direction: column; gap: 1.5rem;">
            <div>
                <h3 style="font-size: 1rem; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.05em;">Courier Information</h3>
                <p style="font-size: 1.25rem; font-weight: 500; margin: 0;">{{ $shipment->courier ?? 'Standard Delivery' }}</p>
                <p style="font-size: 0.9rem; color: var(--accent-secondary); margin-top: 0.25rem;">Tracking: {{ $shipment->tracking_number }}</p>
            </div>
            
            <div style="flex: 1;">
                <h3 style="font-size: 1rem; color: var(--text-muted); margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 0.05em;">Route</h3>
                <div style="position: relative; padding-left: 2rem;">
                    <!-- Line -->
                    <div style="position: absolute; left: 0.4rem; top: 0.5rem; bottom: 0.5rem; width: 2px; background: var(--border-color); border-style: dashed;"></div>
                    
                    <div style="position: relative; margin-bottom: 1.5rem;">
                        <div style="position: absolute; left: -2rem; top: 0.25rem; width: 14px; height: 14px; border-radius: 50%; background: var(--bg-main); border: 2px solid var(--accent-primary);"></div>
                        <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.25rem;">Pickup Address</p>
                        <p style="margin: 0; font-size: 0.95rem;">{{ $shipment->pickup_address }}</p>
                    </div>

                    <div style="position: relative;">
                        <div style="position: absolute; left: -2rem; top: 0.25rem; width: 14px; height: 14px; border-radius: 50%; background: var(--bg-main); border: 2px solid var(--success);"></div>
                        <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.25rem;">Delivery Address</p>
                        <p style="margin: 0; font-size: 0.95rem;">{{ $shipment->delivery_address }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Exchange Summary -->
        <div class="card" style="display: flex; flex-direction: column;">
            <h3 style="font-size: 1rem; color: var(--text-muted); margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 0.05em;">Exchange Summary</h3>
            
            <div class="flex-col gap-4">
                <div style="background: var(--bg-main); padding: 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color); display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--accent-gradient); display: flex; align-items: center; justify-content: center; font-size: 0.8rem; color: white;">
                        {{ substr($shipment->exchange->requester->name, 0, 1) }}
                    </div>
                    <div>
                        <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0;">{{ $shipment->exchange->requester->name }}'s Item</p>
                        <a href="{{ route('items.show', $shipment->exchange->offeredItem) }}" style="font-weight: 500;">{{ $shipment->exchange->offeredItem->title }}</a>
                    </div>
                </div>

                <div style="text-align: center; color: var(--text-muted);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin: 0 auto;"><path d="M16 3h5v5"></path><path d="M4 20L21 3"></path><path d="M21 16v5h-5"></path><path d="M15 15l6 6"></path><path d="M4 4l5 5"></path></svg>
                </div>

                <div style="background: var(--bg-main); padding: 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color); display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--accent-gradient); display: flex; align-items: center; justify-content: center; font-size: 0.8rem; color: white;">
                        {{ substr($shipment->exchange->accepter->name, 0, 1) }}
                    </div>
                    <div>
                        <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0;">{{ $shipment->exchange->accepter->name }}'s Item</p>
                        <a href="{{ route('items.show', $shipment->exchange->requestedItem) }}" style="font-weight: 500;">{{ $shipment->exchange->requestedItem->title }}</a>
                    </div>
                </div>
            </div>
            
            <a href="{{ route('exchanges.show', $shipment->exchange) }}" class="btn btn-secondary w-full" style="margin-top: auto; padding: 0.75rem;">View Full Exchange</a>
        </div>
    </div>

    @if(auth()->check() && auth()->user()->isAdmin())
        <!-- Admin Update Status -->
        <div class="card" style="border: 1px dashed var(--accent-primary);">
            <h3 style="font-size: 1rem; color: var(--accent-primary); margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 0.05em;">Admin Control: Update Status</h3>
            <form action="{{ route('shipments.update', $shipment) }}" method="POST" class="flex items-center gap-4">
                @csrf
                @method('PUT')
                <select name="status" class="input" style="flex: 1;">
                    <option value="order_placed" @if($shipment->status == 'order_placed') selected @endif>Order Placed</option>
                    <option value="picked_up" @if($shipment->status == 'picked_up') selected @endif>Picked Up</option>
                    <option value="in_transit" @if($shipment->status == 'in_transit') selected @endif>In Transit</option>
                    <option value="out_for_delivery" @if($shipment->status == 'out_for_delivery') selected @endif>Out for Delivery</option>
                    <option value="delivered" @if($shipment->status == 'delivered') selected @endif>Delivered</option>
                </select>
                <button type="submit" class="btn btn-primary" style="white-space: nowrap;">Update Status</button>
            </form>
        </div>
    @endif
</div>
</x-app-layout>
