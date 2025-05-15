jQuery(function () {

    const $plugin = jQuery('.plugin__simplenavi_filter');
    if (!$plugin.length) return;

    const $box = jQuery('<input>')
        .addClass('edit')
        .attr('placeholder', LANG.plugins.simplenavi.filter)
        .val(window.sessionStorage.getItem('simplenavi-filter'));

    $box.on('input', function (e) {
        const value = e.target.value;
        window.sessionStorage.setItem('simplenavi-filter', value);
        const lookup = new RegExp(value, 'i');
        $plugin.find('li.hidden').removeClass('hidden');
        $plugin.find('> ul > li > .li').filter(function () {
            return !this.textContent.match(lookup);
        }).parents('li').addClass('hidden');
    });

    $plugin.prepend($box);
    $box.trigger('input');
});
