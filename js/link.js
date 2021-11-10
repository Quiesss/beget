$(document).ready(function() {
    let toastElList = [].slice.call(document.querySelectorAll('.toast'))
    let toastList = toastElList.map(function(toastEl) {
        return new bootstrap.Toast(toastEl)
    });
    let select = $('.toast');
    select.toast({
        'autohide': true,
        'delay': 7000
    });
     // select.toast('show');


        $('.domain_link').on('submit', function (e) {
        e.preventDefault();
        let tr = this.closest("tr");
        let clone = $('.toast:first').clone().appendTo('.notifications');
        clone.toast({'delay': 4000});
        let span = $(this).find('.loadPlace');
        let btn = $('.linkBtn')
        // $(this).find('span').text('asd');
        $.ajax({
            type: "POST",
            url: "/linkHost",
            data: new FormData(this),
            processData: false,
            contentType: false,
            cache: false,
            beforeSend: function () {
                btn.attr('disabled', '');
                span.attr('style', '')},
            complete: function (){
                btn.removeAttr('disabled');
                span.attr('style', 'opacity: 0');
                //setTimeout(span.remove(), 1000);
            },
            success: function(data) {
                let result = JSON.parse(data);
                // $('.notifications').append(clone);
                if(result.w === 1) {
                    $('.toast:last').attr('style', 'right: 0; background: #8cc38c96; color: #523d3d;')
                    setTimeout(tr.parentElement.removeChild(tr), 41000);
                } else {
                    $('.toast:last').attr('style', 'right: 0; background: mistyrose;')
                }
                $('.toast-body:last').html(result.q);
                clone.toast('show');

            },
            error: function(xhr, status, error) {
                console.log('something wrong!' + xhr + status + error);
            }
        });
    });

    $('.changeToken').on('submit', function (e) {
        e.preventDefault();
        $.ajax({
            type: "POST",
            url: "/settings",
            data: new FormData(this),
            processData: false,
            contentType: false,
            cache: false,
            success: function(data) {
                $('.toast-body').html(data);
                select.toast('show');
            },
        })
    });

});



