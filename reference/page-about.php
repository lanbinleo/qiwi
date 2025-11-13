<?php
/**
 * 关于页面
 *
 * @package custom
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
?>

<div class="container main-wrapper">
  <main class="content-area">
    <div class="about-page">
      <!-- 顶部个人信息卡片 -->
      <section class="about-profile-card">
        <div class="profile-avatar">
          <img src="<?php echo $this->options->aboutAvatar ?? 'https://gravatar.loli.net/avatar/default?s=200&d=mp'; ?>" 
               alt="<?php echo $this->options->aboutName ?? '头像'; ?>" 
               class="avatar-image"
               onerror="this.src='https://gravatar.loli.net/avatar/default?s=200&d=mp'">
        </div>
        <div class="profile-info">
          <h1 class="profile-name"><?php echo $this->author->screenName; ?></h1>
          <p class="profile-bio"><?php echo $this->options->aboutBio ?? '在设置里面修改这个噢'; ?></p>
        </div>
      </section>

      <!-- 页面内容卡片 -->
      <section class="about-content-card">
        <article class="single-page" itemscope itemtype="http://schema.org/WebPage">
          <!-- Page Header -->
          <header class="page-header">
            <h1 class="page-title" itemprop="name headline">
              <?php $this->title() ?>
            </h1>

            <?php if ($this->is('page') && $this->getPageType() !== 'post'): ?>
            <div class="page-meta">
              <span class="page-date">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                  <line x1="16" y1="2" x2="16" y2="6"></line>
                  <line x1="8" y1="2" x2="8" y2="6"></line>
                  <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                最后更新: <?php echo date('Y年m月d日', $this->modified); ?>
              </span>
            </div>
            <?php endif; ?>
          </header>

          <!-- Page Content -->
          <div class="page-content" itemprop="text">
            <?php $this->content(); ?>
          </div>
        </article>
      </section>

      <!-- 社交媒体链接 -->
      <?php if ($this->options->aboutSocialLinks): ?>
      <section class="about-social-card">
        <h3 class="social-title">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 8h1a4 4 0 0 1 0 8h-1"></path>
            <path d="M6 8H5a4 4 0 0 0 0 8h1"></path>
            <line x1="10" y1="8" x2="14" y2="8"></line>
          </svg>
          找到我
        </h3>
        <div class="social-links">
          <?php
          $socialLinks = json_decode($this->options->aboutSocialLinks, true);
          if ($socialLinks && is_array($socialLinks)) {
            foreach ($socialLinks as $link) {
              echo '<a href="' . htmlspecialchars($link['url']) . '" target="_blank" rel="noopener noreferrer" class="social-link" title="' . htmlspecialchars($link['name']) . '">';
              echo '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">';
              echo $link['icon'];
              echo '</svg>';
              echo '<span>' . htmlspecialchars($link['name']) . '</span>';
              echo '</a>';
            }
          }
          ?>
        </div>
      </section>
      <?php endif; ?>

      <!-- 技能标签 -->
      <?php if ($this->options->aboutSkills): ?>
      <section class="about-skills-card">
        <h3 class="skills-title">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"></polygon>
          </svg>
          技能标签
        </h3>
        <div class="skills-tags">
          <?php
          $skills = json_decode($this->options->aboutSkills, true);
          if ($skills && is_array($skills)) {
            foreach ($skills as $skill) {
              echo '<span class="skill-tag">' . htmlspecialchars($skill) . '</span>';
            }
          }
          ?>
        </div>
      </section>
      <?php endif; ?>
    </div>

    <!-- Comments Section -->
    <div class="comments-section">
      <?php $this->need('comments.php'); ?>
    </div>
    
  </main>

  <aside class="sidebar">
    <?php $this->need('sidebar.php'); ?>
  </aside>
</div>

</main>
<?php $this->need('footer.php'); ?>