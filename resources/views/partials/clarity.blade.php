{{-- Microsoft Clarity: how people use the app (taps, scrolls, where they stop). Text typed in fields is masked by Clarity. --}}
@if (preg_match('/^[a-z0-9]{6,20}$/i', (string) config('services.clarity.project_id')))
<script>
(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})(window,document,"clarity","script",@json(config('services.clarity.project_id')));
</script>
@endif
