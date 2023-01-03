jQuery(function () {

    const $plugin = jQuery('.plugin__simplenavi_filter');
    if (!$plugin.length) return;

    const $box = jQuery('<input>')
        .addClass('edit')
        .attr('placeholder', LANG.plugins.simplenavi.filter)
        .val(window.sessionStorage.getItem('simplenavi-filter'));

    $box.on('input', function () {
        window.sessionStorage.setItem('simplenavi-filter', $box.val());
        const lookup = new RegExp($box.val(), 'i');
        $plugin.find('li.hidden').removeClass('hidden');
        $plugin.find('> ul > li > .li > a').filter(function () {
            return !this.text.match(lookup);
        }).parents('li').addClass('hidden');
    });

    $plugin.prepend($box);
    $box.trigger('input');
});
