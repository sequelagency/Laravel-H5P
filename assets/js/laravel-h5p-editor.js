/* 
 *
 * @Project        
 * @Copyright      Djoudi
 * @Created        2018-02-20
 * @Filename       laravel-h5p-editor.js
 * @Description    
 *
 */
(function ($) {

    var _document = document;
    if (window.parent) {
        _document = window.parent.document;
    }

    // setting for inside editor
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]', _document).attr('content'),
        },
        dataType: 'json',
    });

})(H5P.jQuery);
