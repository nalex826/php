<?php
/**
 * The template for displaying all custom pages.
 *
 * Template Name: Custom Funnel Display Page
 */
get_header();
get_template_part('template-parts/partial/partial', 'menu-funnel');
?>
<main id="custom-template">
    <?php
        /* Start the Loop */
        while (have_posts()) {
            // Get Hero Template
            get_template_part('template-parts/partial/partial', 'hero-funnel');

            the_post();
            get_template_part('template-parts/content/content', 'funnel');
        } // End of the loop.
?>
</main>
<?php
get_template_part('template-parts/partial/partial', 'footer-funnel');
get_footer();
?>