jQuery(function ($) {
    $('.publishpress-authors-layout-editor').each(function () {
        $(this).css('width', '100%');
        $(this).css('height', '800px');

        let editor = ace.edit(this);
        let TwigMode = ace.require('ace/mode/twig').Mode;

        editor.setTheme('ace/theme/monokai');
        editor.setFontSize(14);
        editor.session.setMode(new TwigMode());
        editor.session.setValue(atob($(this).data('code')));

        let $textarea = $($(this).data('textarea-selector'));
        let moveCodeToTextAreaForSaving = function (e) {
            $textarea.val(editor.getSession().getValue());
        };
        $textarea.closest('form').on('submit', moveCodeToTextAreaForSaving);
    });
});
