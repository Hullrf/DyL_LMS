<div style="text-align: justify;">
    @if(strip_tags($slot) !== $slot)
        {!! $slot !!}
    @else
        {!! nl2br(e($slot)) !!}
    @endif
</div>
