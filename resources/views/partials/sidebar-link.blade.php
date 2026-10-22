<a href="{{ $item['href'] }}" class="sidebar-link {{ $item['active'] ? 'active' : '' }}">
    <span class="sidebar-link-icon"><i class="{{ $item['icon'] ?: 'bi bi-circle' }}"></i></span>
    <span class="sidebar-link-text">{{ $item['title'] }}</span>
</a>
