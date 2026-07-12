// 外链预览：进入视口后再加载，失败时保留可点击的域名卡片。
(function(){
    const VISIBILITY_MARGIN = '200px';
    const MAX_CONCURRENCY = 2;
    const FETCH_TIMEOUT = 4500;
    const tasks = [];
    let inflight = 0;

    function runQueue(){
        while (inflight < MAX_CONCURRENCY && tasks.length) {
            const fn = tasks.shift();
            inflight++;
            Promise.resolve().then(fn).finally(() => {
                inflight--;
                runQueue();
            });
        }
    }

    function enqueue(fn){
        tasks.push(fn);
        runQueue();
    }

    function fetchWithTimeout(url, opts = {}, timeout = FETCH_TIMEOUT){
        if (typeof AbortController === 'undefined') {
            return fetch(url, opts);
        }
        const ctl = new AbortController();
        const id = setTimeout(() => ctl.abort(), timeout);
        return fetch(url, { ...opts, signal: ctl.signal }).finally(() => clearTimeout(id));
    }

    function hostnameOf(href){
        try {
            return new URL(href).hostname;
        } catch (e) {
            return href;
        }
    }

    function ensurePreview(link){
        if (link.dataset.hasPreview) return;
        link.dataset.hasPreview = '1';
        if (!/^https?:\/\//.test(link.href)) return;

        const preview = document.createElement('a');
        preview.className = 'link-preview';
        preview.href = link.href;
        preview.target = '_blank';
        preview.rel = 'noopener noreferrer';

        const img = document.createElement('img');
        img.className = 'link-preview-thumb';
        img.alt = '';
        img.loading = 'lazy';
        img.decoding = 'async';
        img.src = `https://www.google.com/s2/favicons?sz=64&domain_url=${encodeURIComponent(link.href)}`;

        const contentDiv = document.createElement('div');
        contentDiv.className = 'link-preview-content';
        const titleDiv = document.createElement('div');
        titleDiv.className = 'link-preview-title';
        titleDiv.textContent = link.textContent.trim() || link.href;
        const descDiv = document.createElement('div');
        descDiv.className = 'link-preview-desc';
        descDiv.textContent = '正在读取链接摘要';
        const domainDiv = document.createElement('div');
        domainDiv.className = 'link-preview-domain';
        domainDiv.textContent = hostnameOf(link.href);

        contentDiv.appendChild(titleDiv);
        contentDiv.appendChild(descDiv);
        contentDiv.appendChild(domainDiv);
        preview.appendChild(img);
        preview.appendChild(contentDiv);
        link.parentNode.insertBefore(preview, link.nextSibling);

        enqueue(() => fetchWithTimeout(`https://jsonlink.io/api/extract?url=${encodeURIComponent(link.href)}`)
            .then(res => res.ok ? res.json() : Promise.reject())
            .then(data => {
                if (!preview.isConnected) return;
                if (data.title) titleDiv.textContent = data.title;
                descDiv.textContent = data.description || '点击打开原链接';
                if (data.images && data.images.length > 0) {
                    img.src = data.images[0];
                }
            })
            .catch(() => {
                if (preview.isConnected) {
                    descDiv.textContent = '预览暂不可用，点击打开原链接';
                    preview.classList.add('is-fallback');
                }
            })
        );
    }

    const containerLinks = Array.from(document.querySelectorAll('.content .external-link'));
    if ('IntersectionObserver' in window) {
        const io = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    ensurePreview(entry.target);
                    io.unobserve(entry.target);
                }
            });
        }, { root: null, rootMargin: VISIBILITY_MARGIN, threshold: 0.01 });
        containerLinks.forEach(link => io.observe(link));
    } else {
        containerLinks.forEach(ensurePreview);
    }
})();

// 手机端默认收起时间归档，桌面端保持展开。
(function(){
    const archive = document.querySelector('.archive-section');
    if (!archive || !window.matchMedia) return;

    const media = window.matchMedia('(max-width: 640px)');
    function syncArchiveState(event) {
        if (event.matches) {
            archive.removeAttribute('open');
        } else {
            archive.setAttribute('open', '');
        }
    }

    syncArchiveState(media);
    if (typeof media.addEventListener === 'function') {
        media.addEventListener('change', syncArchiveState);
    } else if (typeof media.addListener === 'function') {
        media.addListener(syncArchiveState);
    }
})();

// 图片灯箱：支持键盘、触摸滑动、相邻图片预加载。
(function(){
    const lightbox = document.getElementById('lightbox');
    if (!lightbox) return;

    const lightboxImg = document.getElementById('lightbox-img');
    const lightboxClose = lightbox.querySelector('.lightbox-close');
    const lightboxPrev = lightbox.querySelector('.lightbox-prev');
    const lightboxNext = lightbox.querySelector('.lightbox-next');
    const lightboxBackdrop = lightbox.querySelector('.lightbox-backdrop');
    const lightboxIndex = lightbox.querySelector('.lightbox-index');
    let currentSet = [];
    let currentIdx = 0;
    let touchStartX = null;

    document.querySelectorAll('.image-gallery').forEach(gallery => {
        const imgs = Array.from(gallery.querySelectorAll('img'));
        imgs.forEach((img, idx) => {
            img.addEventListener('click', e => {
                e.stopPropagation();
                currentSet = imgs.map(item => item.src);
                currentIdx = idx;
                showLightbox();
            });
        });
    });

    function preloadNeighbors(){
        [currentIdx - 1, currentIdx + 1].forEach(idx => {
            if (currentSet[idx]) {
                const img = new Image();
                img.src = currentSet[idx];
            }
        });
    }

    function showLightbox() {
        updateLightbox();
        lightbox.style.display = '';
        lightbox.setAttribute('aria-hidden', 'false');
        setTimeout(() => lightbox.classList.add('show'), 10);
        document.body.style.overflow = 'hidden';
    }

    function hideLightbox() {
        lightbox.classList.remove('show');
        lightbox.setAttribute('aria-hidden', 'true');
        setTimeout(() => {
            lightbox.style.display = 'none';
            document.body.style.overflow = '';
        }, 250);
    }

    function updateLightbox() {
        if (!currentSet.length) return;
        lightboxImg.classList.remove('fade-in');
        setTimeout(() => {
            lightboxImg.src = currentSet[currentIdx];
            lightboxImg.classList.add('fade-in');
            lightboxIndex.textContent = `${currentIdx + 1} / ${currentSet.length}`;
            lightboxPrev.style.display = currentSet.length > 1 && currentIdx > 0 ? 'flex' : 'none';
            lightboxNext.style.display = currentSet.length > 1 && currentIdx < currentSet.length - 1 ? 'flex' : 'none';
            preloadNeighbors();
        }, 10);
    }

    function prevImg(){
        if (currentIdx <= 0) return;
        currentIdx--;
        updateLightbox();
    }

    function nextImg(){
        if (currentIdx >= currentSet.length - 1) return;
        currentIdx++;
        updateLightbox();
    }

    lightboxClose.addEventListener('click', hideLightbox);
    lightboxBackdrop.addEventListener('click', hideLightbox);
    lightboxPrev.addEventListener('click', prevImg);
    lightboxNext.addEventListener('click', nextImg);

    lightbox.addEventListener('touchstart', e => {
        touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });

    lightbox.addEventListener('touchend', e => {
        if (touchStartX === null) return;
        const diff = e.changedTouches[0].screenX - touchStartX;
        if (Math.abs(diff) > 45) {
            diff > 0 ? prevImg() : nextImg();
        }
        touchStartX = null;
    }, { passive: true });

    document.addEventListener('keydown', e => {
        if (lightbox.style.display === 'none') return;
        if (e.key === 'Escape') hideLightbox();
        if (e.key === 'ArrowLeft') prevImg();
        if (e.key === 'ArrowRight') nextImg();
    });
})();
