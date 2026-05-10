<x-app-layout>
    <section class="card">
        <h2>Edit Item</h2>
        <form method="POST" action="{{ route('items.update', $item) }}" enctype="multipart/form-data" class="grid grid-3">
            @csrf
            @method('PUT')
            <div><label>Title</label><input name="title" value="{{ old('title', $item->title) }}" required></div>
            <div>
                <label>Category</label>
                <select name="category" required>
                    <option value="">Select subcategory</option>
                    @foreach($parentCategories as $parent => $subs)
                        <optgroup label="{{ $parent }}">
                            @foreach($subs as $sub)
                                <option value="{{ $sub }}" @selected(old('category', $item->category) === $sub)>{{ $sub }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div><label>Condition</label>
                <select name="condition" required>
                    @foreach($conditions as $condition)
                        <option value="{{ $condition }}" @selected(old('condition', $item->condition) === $condition)>{{ ucfirst($condition) }}</option>
                    @endforeach
                </select>
            </div>
            <div><label>Item age</label><input name="item_age" value="{{ old('item_age', $item->item_age) }}" required></div>
            <div><label>Price (INR)</label><input name="price" type="number" step="0.01" value="{{ old('price', $item->price) }}" required></div>
            <div><label>Location</label><input name="location" value="{{ old('location', $item->location) }}" required></div>
            <div><label>Update bill (optional)</label><input name="bill" type="file" accept=".pdf,.jpg,.jpeg,.png"></div>
            <div style="grid-column:1 / -1;"><label>Description</label><textarea name="description">{{ old('description', $item->description) }}</textarea></div>
            <div style="grid-column:1 / -1;"><label>Notes</label><textarea name="notes">{{ old('notes', $item->notes) }}</textarea></div>
            @if($item->bill_url)
                <div style="grid-column:1 / -1;"><a class="btn" href="{{ $item->bill_url }}" target="_blank" rel="noopener">View current bill</a></div>
            @endif
            <div style="grid-column:1 / -1;"><button class="btn btn-primary" type="submit">Save Changes</button></div>
        </form>
    </section>
</x-app-layout>