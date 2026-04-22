<div class="page-header">
    <div class="page-title-group">
        <h1 class="page-title">{{ $title }}</h1>
        @if(!empty($subtitle))
            <p class="page-subtitle">{{ $subtitle }}</p>
        @endif
    </div>
    @if(!empty($actions))
        <div class="page-actions">{!! implode('', $actions) !!}</div>
    @endif
</div>
