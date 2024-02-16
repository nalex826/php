<?php
/**
 * Template part for displaying News Page.
 */
$language      = get_query_var('lang');
$localpath     = get_template_directory_uri() . '/assets/images/';
$categories    = group_categories('news_categories');
// Fetch News Post
$heroNews      = (new News([], 3))->fetch_news_posts($language);
$news          = (new News($_GET))->fetch_news_posts($language);
?>
<article id="news">
    <section id="hero">
        <div class="hero-item bkg-asset" style="background-image: url('<?php echo $localpath; ?>news_bkg.jpg')">
            <h1 class="text-center display-1 white"><?php the_title(); ?></h1>
            <?php get_template_part('templates/partial/partial', 'news-slider', ['news' => $heroNews->posts, 'hide_date' => 1, 'hide_excerpt' => 1, 'override' => 'slider-hero-news']); ?>
        </div>
    </section>
    <section class="container-fluid fixed-wrapper">
        <?php if (! empty($categories[$language]['child'])) { ?>
        <div class="sidemenu d-lg-none">
            <ul class="list-unstyled text-uppercase filter bold">
                <li><a href="<?php echo $language; ?>/news/" class="<?php echo (empty($_GET['category'])) ? 'selected' : ''; ?>"><?php _t('All'); ?></a></li>
                <?php foreach ($categories[$language]['child'] as $group) {
                    $selected = (! empty($_GET['category']) && $group['slug'] == $_GET['category']) ? 'selected' : ''; ?>
                <li><a href="<?php echo $language . '/news/category/' . $group['slug']; ?>" class="<?php echo $selected; ?>"><?php echo $group['name']; ?></a></li>
                <?php } ?>
            </ul>
        </div>
        <?php } ?>
        <div class="d-flex justify-content-between">
            <div class="wrapper">
                <?php get_template_part('templates/partial/partial', 'news', ['news' => $news]); ?>
            </div>
            <?php if (! empty($categories[$language]['child'])) { ?>
            <div class="sidemenu d-none d-lg-block">
                <ul class="list-unstyled text-uppercase filter bold">
                    <li><a href="<?php echo $language; ?>/news/" class="<?php echo (empty($_GET['category'])) ? 'selected' : ''; ?>"><?php _t('All'); ?></a></li>
                    <?php foreach ($categories[$language]['child'] as $group) {
                        $selected = (! empty($_GET['category']) && $group['slug'] == $_GET['category']) ? 'selected' : ''; ?>
                    <li><a href="<?php echo $language . '/news/category/' . $group['slug']; ?>" class="<?php echo $selected; ?>"><?php echo $group['name']; ?></a></li>
                    <?php } ?>
                </ul>
            </div>
            <?php } ?>
        </div>
    </section>
</article>