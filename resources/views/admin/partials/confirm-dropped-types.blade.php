{{--
    FR-009: before $form is sent, lists the enabled pairs it turns off — unchecked boxes and enabled pairs of retired
    types, both marked data-enabled-code — and asks. Browser-side only (specs/002-admin-web-console/research.md R4).
--}}
<script>
    document.getElementById(@js($form)).addEventListener('submit', function (event) {
        var dropped = Array.prototype.filter.call(this.querySelectorAll('[data-enabled-code]'), function (element) {
            return element.checked !== true;
        }).map(function (element) {
            return element.getAttribute('data-enabled-code');
        });

        if (dropped.length > 0 && !confirm(@js('Выдача номеров остановится по типам: ') + dropped.join(', ') + @js('. Сохранить набор?'))) {
            event.preventDefault();
        }
    });
</script>
