jQuery(document).ready(function($) {
    $('.view-location').on('click', function() {
        alert('经纬度：' + $(this).data('lat') + ', ' + $(this).data('lng'));
    });

    $('.view-gpu').on('click', function() {
        alert('GPU 信息：\n' + $(this).data('gpu'));
    });

    $('.view-ua').on('click', function() {
        alert('User Agent：\n' + $(this).data('ua'));
    });

    $('.view-uach').on('click', function() {
        alert('User Agent Client Hints：\n' + $(this).data('uach'));
    });
});