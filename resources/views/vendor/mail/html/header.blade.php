@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
    <img src="{{ asset('logo.png') }}" class="logo" alt="{{ config('app.name') }} logo" style="height: 48px; width: auto; display: inline-block;" />
</a>
</td>
</tr>
