@props(['action' => 'submit'])

@php($siteKey = config('services.recaptcha.site_key'))

@if ($siteKey)
    <input type="hidden" name="g-recaptcha-response" value="">

    @once
        <script src="https://www.google.com/recaptcha/api.js?render={{ $siteKey }}"></script>
    @endonce

    <script>
        (function () {
            var form = document.currentScript.closest('form');
            if (!form) return;

            form.addEventListener('submit', function (e) {
                if (form.dataset.recaptchaDone === '1') return;
                e.preventDefault();

                var finish = function (token) {
                    var field = form.querySelector('input[name="g-recaptcha-response"]');
                    if (field) field.value = token || '';
                    form.dataset.recaptchaDone = '1';
                    form.submit();
                };

                if (typeof grecaptcha === 'undefined') { finish(''); return; }

                grecaptcha.ready(function () {
                    grecaptcha.execute(@js($siteKey), { action: @js($action) })
                        .then(finish)
                        .catch(function () { finish(''); });
                });
            });
        })();
    </script>
@endif
