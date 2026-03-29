   <section class="footer-section">
        <div class="container">
            <div class="row">
                <div class="col-md-12 text-center">
                 Online Library Management System developed by akkaladevi.sravan kumar | &copy; 2026 All Rights Reserved
                </div>

            </div>
        </div>
    </section>
<script>
(function () {
    if (window.matchMedia && window.matchMedia('(pointer: coarse)').matches) {
        return;
    }

    var body = document.body;
    if (!body || document.querySelector('.cursor-dot') || document.querySelector('.cursor-ring')) {
        return;
    }

    var dot = document.createElement('div');
    var ring = document.createElement('div');
    var ringX = window.innerWidth / 2;
    var ringY = window.innerHeight / 2;
    var mouseX = ringX;
    var mouseY = ringY;

    dot.className = 'cursor-dot';
    ring.className = 'cursor-ring';
    body.classList.add('has-animated-cursor');
    body.appendChild(dot);
    body.appendChild(ring);

    document.addEventListener('mousemove', function (event) {
        mouseX = event.clientX;
        mouseY = event.clientY;
        dot.style.left = mouseX + 'px';
        dot.style.top = mouseY + 'px';
        body.classList.add('cursor-active');
    });

    document.addEventListener('mouseenter', function () {
        body.classList.add('cursor-active');
    });

    document.addEventListener('mouseleave', function () {
        body.classList.remove('cursor-active', 'cursor-hovering');
    });

    document.addEventListener('mouseover', function (event) {
        if (event.target.closest('a, button, input, select, textarea, label, .btn, .dropdown-toggle')) {
            body.classList.add('cursor-hovering');
        }
    });

    document.addEventListener('mouseout', function (event) {
        if (event.target.closest('a, button, input, select, textarea, label, .btn, .dropdown-toggle')) {
            body.classList.remove('cursor-hovering');
        }
    });

    function animateRing() {
        ringX += (mouseX - ringX) * 0.18;
        ringY += (mouseY - ringY) * 0.18;
        ring.style.left = ringX + 'px';
        ring.style.top = ringY + 'px';
        window.requestAnimationFrame(animateRing);
    }

    window.requestAnimationFrame(animateRing);
}());
</script>
