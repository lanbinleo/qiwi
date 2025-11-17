/**
 * 一言打字机效果
 */
(function() {
    'use strict';

    const wrapper = document.querySelector('[data-enable-hitokoto="true"]');
    if (!wrapper) return;

    const mottoText = wrapper.querySelector('.motto-text');
    const mottoElement = wrapper.querySelector('.site-motto');
    const typingCursor = wrapper.querySelector('.typing-cursor');
    const bio = wrapper.dataset.bio;

    const CONFIG = {
        TYPING_SPEED: 100,      // 打字速度：100ms/字符
        PAUSE_AFTER_BIO: 2000,  // bio展示后停顿2秒
        PAUSE_AFTER_HITOKOTO: 8000, // 一言展示后停顿8秒
        BIO_PROBABILITY: 0.5,   // 50%概率展示bio
        API_URL: 'https://v1.hitokoto.cn'
    };

    let isTyping = false;
    let currentText = '';
    let currentTimer = null;
    let pauseTimer = null;

    /**
     * 停止所有动画
     */
    function stopAllAnimations() {
        if (currentTimer) {
            clearInterval(currentTimer);
            currentTimer = null;
        }
        if (pauseTimer) {
            clearTimeout(pauseTimer);
            pauseTimer = null;
        }
        isTyping = false;
    }

    /**
     * 打字机效果
     */
    function typeText(text, callback) {
        if (isTyping) return;
        isTyping = true;
        currentText = text;

        let index = 0;
        mottoText.textContent = '';
        typingCursor.style.display = 'inline';

        currentTimer = setInterval(() => {
            if (index < text.length) {
                mottoText.textContent += text[index];
                index++;
            } else {
                clearInterval(currentTimer);
                currentTimer = null;
                isTyping = false;
                if (callback) callback();
            }
        }, CONFIG.TYPING_SPEED);
    }

    /**
     * 清空文本（反向打字机效果）
     */
    function clearText(callback) {
        if (isTyping) return;
        isTyping = true;

        const text = mottoText.textContent;
        let index = text.length;

        currentTimer = setInterval(() => {
            if (index > 0) {
                mottoText.textContent = text.substring(0, index - 1);
                index--;
            } else {
                clearInterval(currentTimer);
                currentTimer = null;
                isTyping = false;
                if (callback) callback();
            }
        }, CONFIG.TYPING_SPEED / 2);
    }

    /**
     * 获取一言
     */
    async function fetchHitokoto() {
        try {
            const response = await fetch(CONFIG.API_URL);
            const data = await response.json();
            const source = data.from_who ? `${data.from} - ${data.from_who}` : data.from;
            // 将来源整合到文本中
            const fullText = `${data.hitokoto} —— ${source}`;
            return fullText;
        } catch (error) {
            console.error('获取一言失败:', error);
            return null;
        }
    }

    /**
     * 展示一言
     */
    async function showHitokoto() {
        const hitokoto = await fetchHitokoto();
        if (!hitokoto) {
            // 如果获取失败，等待后重试
            pauseTimer = setTimeout(showHitokoto, CONFIG.PAUSE_AFTER_HITOKOTO);
            return;
        }

        typeText(hitokoto, () => {
            // 等待后切换到下一条一言
            pauseTimer = setTimeout(() => {
                clearText(() => {
                    showHitokoto();
                });
            }, CONFIG.PAUSE_AFTER_HITOKOTO);
        });
    }

    /**
     * 复制当前文本
     */
    function copyCurrentText() {
        const textToCopy = currentText;
        if (!textToCopy) return;

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(textToCopy).then(() => {
                showCopyFeedback();
            }).catch(() => {
                fallbackCopy(textToCopy);
            });
        } else {
            fallbackCopy(textToCopy);
        }
    }

    /**
     * 传统复制方法
     */
    function fallbackCopy(text) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        document.body.appendChild(textArea);
        textArea.select();
        try {
            document.execCommand('copy');
            showCopyFeedback();
        } catch (err) {
            console.error('复制失败:', err);
        }
        document.body.removeChild(textArea);
    }

    /**
     * 显示复制反馈
     */
    function showCopyFeedback() {
        const originalOpacity = mottoElement.style.opacity;
        mottoElement.style.opacity = '0.5';
        setTimeout(() => {
            mottoElement.style.opacity = originalOpacity;
        }, 200);
    }

    /**
     * 初始化
     */
    function init() {
        // 50%概率决定是否先展示bio
        const shouldShowBio = Math.random() < CONFIG.BIO_PROBABILITY;

        if (shouldShowBio && bio) {
            // 先展示bio
            typeText(bio, () => {
                pauseTimer = setTimeout(() => {
                    clearText(() => {
                        showHitokoto();
                    });
                }, CONFIG.PAUSE_AFTER_BIO);
            });
        } else {
            // 直接展示一言
            showHitokoto();
        }

        // 左键点击复制
        mottoElement.addEventListener('click', (e) => {
            e.preventDefault();
            copyCurrentText();
        });

        // 右键点击重新获取
        mottoElement.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            stopAllAnimations();
            mottoText.textContent = '';
            showHitokoto();
        });
    }

    // 页面加载完成后初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
