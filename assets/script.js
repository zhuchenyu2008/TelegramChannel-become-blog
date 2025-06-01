// 标签点击行为 - 跳转到后端筛选
document.querySelectorAll('.tag').forEach(tag => {
    tag.addEventListener('click', e => {
        e.preventDefault();
        // Extract tag name from href (safer than textContent if there are other characters)
        let tagName = '';
        try {
            const url = new URL(tag.href, window.location.origin);
            tagName = url.searchParams.get('tag');
        } catch (error) {
            console.error("Error parsing tag href:", error);
            // Fallback or alternative if href is not a full URL or doesn't have ?tag=
            // For example, if href is just "#someTag" or textContent is "#someTag"
            const text = tag.textContent.trim();
            if (text.startsWith('#')) {
                tagName = text.substring(1);
            }
        }

        if (tagName) {
            window.location.href = `index.php?tag=${encodeURIComponent(tagName)}`;
        } else {
            // Fallback to root if tag name couldn't be extracted (should not happen with current HTML)
            window.location.href = 'index.php';
        }
    });
});

// Client-side filtering and back button logic previously here are now removed,
// as filtering is handled by the backend and navigation is via direct links.

// --- 生成链接预览框 ---
document.querySelectorAll('.content').forEach(contentEl => {
    contentEl.querySelectorAll('.external-link').forEach(link => {
        if (link.dataset.hasPreview) return;
        link.dataset.hasPreview = "1";
        if (!/^https?:\/\//.test(link.href)) return;
        const preview = document.createElement('a');
        preview.className = 'link-preview';
        preview.href = link.href;
        preview.target = '_blank';
        preview.rel = 'noopener noreferrer';

        // Clear any existing content in preview (though it's a new element, good practice)
        preview.innerHTML = ''; 

        // Create image element
        const img = document.createElement('img');
        img.className = 'link-preview-thumb';
        img.src = `https://www.google.com/s2/favicons?sz=64&domain_url=${encodeURIComponent(link.href)}`;
        img.alt = 'icon';

        // Create content container
        const contentDiv = document.createElement('div');
        contentDiv.className = 'link-preview-content';

        // Create title element
        const titleDiv = document.createElement('div');
        titleDiv.className = 'link-preview-title';
        titleDiv.textContent = link.href; // Use textContent

        // Create description element
        const descDiv = document.createElement('div');
        descDiv.className = 'link-preview-desc';
        descDiv.textContent = '正在加载预览…';

        // Create domain element
        const domainDiv = document.createElement('div');
        domainDiv.className = 'link-preview-domain';
        // Make sure link.href is a valid URL before trying to get hostname
        try {
            domainDiv.textContent = (new URL(link.href)).hostname; // Use textContent
        } catch (e) {
            domainDiv.textContent = link.href; // Fallback if URL parsing fails
        }
        
        // Append elements
        contentDiv.appendChild(titleDiv);
        contentDiv.appendChild(descDiv);
        contentDiv.appendChild(domainDiv);

        preview.appendChild(img);
        preview.appendChild(contentDiv);
        
        link.parentNode.insertBefore(preview, link.nextSibling);
        fetch(`https://jsonlink.io/api/extract?url=${encodeURIComponent(link.href)}`)
        .then(res => res.json())
        .then(data => {
            if (data.title) {
                preview.querySelector('.link-preview-title').textContent = data.title;
            }
            if (data.description) {
                preview.querySelector('.link-preview-desc').textContent = data.description;
            } else {
                preview.querySelector('.link-preview-desc').textContent = '';
            }
            if (data.images && data.images.length > 0) {
                preview.querySelector('.link-preview-thumb').src = data.images[0];
            }
        })
        .catch(() => {
            preview.querySelector('.link-preview-desc').textContent = '';
        });
    });
});
// --------- 图片灯箱和多图浏览 ----------
(function(){
    // 灯箱元素
    const lightbox = document.getElementById('lightbox');
    const lightboxImg = document.getElementById('lightbox-img');
    const lightboxClose = lightbox.querySelector('.lightbox-close');
    const lightboxPrev = lightbox.querySelector('.lightbox-prev');
    const lightboxNext = lightbox.querySelector('.lightbox-next');
    const lightboxBackdrop = lightbox.querySelector('.lightbox-backdrop');
    const lightboxIndex = lightbox.querySelector('.lightbox-index');
    let currentSet = [];
    let currentIdx = 0;

    // 所有帖子图片监听
    document.querySelectorAll('.image-gallery').forEach(gallery => {
        const imgs = Array.from(gallery.querySelectorAll('img'));
        imgs.forEach((img, idx) => {
            img.addEventListener('click', function(e){
                e.stopPropagation();
                currentSet = imgs.map(i=>i.src);
                currentIdx = idx;
                showLightbox();
            });
        });
    });

    function showLightbox() {
        updateLightbox();
        lightbox.style.display = '';
        setTimeout(()=>lightbox.classList.add('show'), 10);
        document.body.style.overflow = 'hidden';
    }
    function hideLightbox() {
        lightbox.classList.remove('show');
        setTimeout(()=>{lightbox.style.display = 'none'; document.body.style.overflow='';}, 300);
    }
    function updateLightbox() {
        lightboxImg.classList.remove('fade-in');
        setTimeout(()=>{
            lightboxImg.src = currentSet[currentIdx];
            lightboxImg.classList.add('fade-in');
            lightboxIndex.textContent = (currentIdx+1) + ' / ' + currentSet.length;

            // Conditionally show/hide navigation arrows
            if (currentSet.length <= 1) {
                lightboxPrev.style.display = 'none';
                lightboxNext.style.display = 'none';
            } else {
                if (currentIdx === 0) {
                    lightboxPrev.style.display = 'none';
                } else {
                    lightboxPrev.style.display = 'flex';
                }

                if (currentIdx === currentSet.length - 1) {
                    lightboxNext.style.display = 'none';
                } else {
                    lightboxNext.style.display = 'flex';
                }
            }
        }, 10);
    }
    function prevImg(){
        currentIdx = (currentIdx - 1 + currentSet.length) % currentSet.length;
        updateLightbox();
    }
    function nextImg(){
        currentIdx = (currentIdx + 1) % currentSet.length;
        updateLightbox();
    }
    lightboxClose.onclick = hideLightbox;
    lightboxBackdrop.onclick = hideLightbox;
    lightboxPrev.onclick = prevImg;
    lightboxNext.onclick = nextImg;

    // 键盘支持
    document.addEventListener('keydown', function(e){
        if(lightbox.style.display === 'none') return;
        if(e.key === 'Escape') hideLightbox();
        if(e.key === 'ArrowLeft') prevImg();
        if(e.key === 'ArrowRight') nextImg();
    });
})();
