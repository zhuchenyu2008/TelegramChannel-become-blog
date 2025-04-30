// 简单平滑滚动到标签
document.querySelectorAll('.tag').forEach(tag => {
    tag.addEventListener('click', e => {
        e.preventDefault();
        const t = new URL(e.target.href).searchParams.get('tag');
        document.querySelectorAll('.post').forEach(post => {
            if (!post.textContent.includes('#' + t)) {
                post.style.display = 'none';
            } else {
                post.style.display = '';
            }
        });
    });
});