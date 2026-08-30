@php($hp = \App\Support\Honeypot::class)
{{-- Decoy field: invisible & unfocusable for humans, tempting for bots. --}}
<div aria-hidden="true" tabindex="-1"
     style="position:absolute!important;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;border:0;">
    <label for="{{ $hp::TRAP }}">Leave this field blank</label>
    <input type="text" id="{{ $hp::TRAP }}" name="{{ $hp::TRAP }}" value="" tabindex="-1" autocomplete="off">
</div>
<input type="hidden" name="{{ $hp::STAMP }}" value="{{ $hp::stamp() }}">
