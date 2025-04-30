// 标签筛选平滑隐藏 + 返回按钮
const backBtn = document.getElementById('backBtn');
document.querySelectorAll('.tag').forEach(tag => {
    tag.addEventListener('click', e => {
        e.preventDefault();
        const t = new URL(tag.href, window.location.href).searchParams.get('tag');
        document.querySelectorAll('.post').forEach(post => {
            // 只显示含有这个tag的post
            let hasTag = false;
            post.querySelectorAll('.tag').forEach(a => {
                if (a.textContent === '#' + t) hasTag = true;
            });
            post.style.display = hasTag ? '' : 'none';
        });
        // 显示返回按钮
        backBtn.style.display = '';
        // 激活当前标签样式
        document.querySelectorAll('.tag').forEach(a => a.classList.remove('active'));
        tag.classList.add('active');
    });
});
backBtn.addEventListener('click', e => {
    e.preventDefault();
    // 显示所有帖子
    document.querySelectorAll('.post').forEach(post => {
        post.style.display = '';
    });
    // 隐藏返回按钮
    backBtn.style.display = 'none';
    // 取消所有激活标签
    document.querySelectorAll('.tag').forEach(a => a.classList.remove('active'));
});

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
        preview.innerHTML = `
            <img class="link-preview-thumb" src="https://www.google.com/s2/favicons?sz=64&domain_url=${encodeURIComponent(link.href)}" alt="icon">
            <div class="link-preview-content">
                <div class="link-preview-title">${link.href}</div>
                <div class="link-preview-desc">正在加载预览…</div>
                <div class="link-preview-domain">${(new URL(link.href)).hostname}</div>
            </div>
        `;
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
