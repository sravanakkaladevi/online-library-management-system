
/*=============================================================
    Authour URI: www.binarytheme.com
    License: Commons Attribution 3.0

    http://creativecommons.org/licenses/by/3.0/

    100% Free To use For Personal And Commercial Use.
    IN EXCHANGE JUST GIVE US CREDITS AND TELL YOUR FRIENDS ABOUT US
   
    ========================================================  */

(function ($) {
    "use strict";
    var mainApp = {
        slide_fun: function () {

            $('#carousel-example').carousel({
                interval:3000 // THIS TIME IS IN MILLI SECONDS
            })

        },
        dataTable_fun: function () {

            $('#dataTables-example').dataTable();

        },
       
        custom_fun:function()
        {
            /*====================================
             WRITE YOUR   SCRIPTS  BELOW
            ======================================*/
            if (window.location && /\/admin\//i.test(window.location.pathname)) {
                return;
            }

            if (window.matchMedia && window.matchMedia('(pointer: coarse)').matches) {
                return;
            }

            if ($('.cursor-dot').length || $('.cursor-ring').length) {
                return;
            }

            var $body = $('body');
            var dot = $('<div class="cursor-dot"></div>');
            var ring = $('<div class="cursor-ring"></div>');
            var ringX = window.innerWidth / 2;
            var ringY = window.innerHeight / 2;
            var mouseX = ringX;
            var mouseY = ringY;

            $body.addClass('has-animated-cursor');
            $body.append(dot, ring);

            $(document).on('mousemove', function (event) {
                mouseX = event.clientX;
                mouseY = event.clientY;
                dot.css({
                    left: mouseX + 'px',
                    top: mouseY + 'px'
                });
                $body.addClass('cursor-active');
            });

            $(document).on('mouseleave', function () {
                $body.removeClass('cursor-active cursor-hovering');
            });

            $(document).on('mouseenter', function () {
                $body.addClass('cursor-active');
            });

            $(document).on('mouseenter', 'a, button, input, select, textarea, label, .btn, .dropdown-toggle', function () {
                $body.addClass('cursor-hovering');
            });

            $(document).on('mouseleave', 'a, button, input, select, textarea, label, .btn, .dropdown-toggle', function () {
                $body.removeClass('cursor-hovering');
            });

            function animateRing() {
                ringX += (mouseX - ringX) * 0.18;
                ringY += (mouseY - ringY) * 0.18;
                ring.css({
                    left: ringX + 'px',
                    top: ringY + 'px'
                });
                window.requestAnimationFrame(animateRing);
            }

            window.requestAnimationFrame(animateRing);
        },

    }
   
   
    $(document).ready(function () {
        mainApp.slide_fun();
        mainApp.dataTable_fun();
        mainApp.custom_fun();
    });
}(jQuery));


