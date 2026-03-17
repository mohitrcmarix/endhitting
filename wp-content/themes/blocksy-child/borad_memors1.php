<?php

/**
 * Template Name: Board Members Page
 * Description: A custom template to display the Board Members custom post type with ACF fields in a 2-column layout.
 */

get_header(); ?>

<main id="main" class="site-main" role="main">
    <div class="ct-container board-members-container" data-content="normal" data-vertical-spacing="top:bottom">
        <header class="entry-header board-members-header">
            <h1 class="entry-title"><?php echo get_the_title(); ?></h1>
        </header>

        <div class="board-members-list">
            <?php
            // Query for the custom post type 'board_members'
            $args = array(
                'post_type'      => 'board_members',
                'posts_per_page' => -1, // Display all
                'orderby'        => 'title',
                'order'          => 'ASC',
            );
            $board_members_query = new WP_Query($args);

            if ($board_members_query->have_posts()) :
                while ($board_members_query->have_posts()) : $board_members_query->the_post();

                    // ACF Fields
                    $member_detail = get_field('detail');
                    $member_email  = get_field('email');

            ?>
                    <article id="post-<?php the_ID(); ?>" class="board-member-row">

                        <!-- LEFT COLUMN: Image & Name -->
                        <div class="bm-left-col">
                            <?php if (has_post_thumbnail()) : ?>
                                <div class="bm-image-wrapper">
                                    <?php the_post_thumbnail('large', array('class' => 'bm-image')); ?>
                                </div>
                            <?php else: ?>
                                <!-- Fallback empty image block if no thumbnail -->
                                <div class="bm-image-wrapper bm-image-empty">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" fill="#ccc" class="bi bi-person-fill" viewBox="0 0 16 16">
                                        <path d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1H3zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z" />
                                    </svg>
                                </div>
                            <?php endif; ?>

                            <h2 class="bm-name"><?php the_title(); ?></h2>

                            <?php if ($member_email) : ?>
                                <div class="bm-email">
                                    <a href="mailto:<?php echo esc_attr($member_email); ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-envelope-fill" viewBox="0 0 16 16">
                                            <path d="M.05 3.555A2 2 0 0 1 2 2h12a2 2 0 0 1 1.95 1.555L8 8.414.05 3.555ZM0 4.697v7.104l5.803-3.558L0 4.697ZM6.761 8.83l-6.57 4.027A2 2 0 0 0 2 14h12a2 2 0 0 0 1.808-1.144l-6.57-4.027L8 9.586l-1.239-.757Zm3.436-.586L16 11.801V4.697l-5.803 3.546Z" />
                                        </svg>
                                        <?php echo esc_html($member_email); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- RIGHT COLUMN: Details & Accordion -->
                        <div class="bm-right-col">
                            <div class="bm-content">
                                <?php
                                if ($member_detail) :
                                    // Strip tags just in case, to get a clean excerpt
                                    $excerpt_length = 250; // Show a longer excerpt for the horizontal layout
                                    $clean_detail = wp_strip_all_tags($member_detail);

                                    // Create a short excerpt
                                    $short_detail = mb_substr($clean_detail, 0, $excerpt_length);
                                    if (mb_strlen($clean_detail) > $excerpt_length) {
                                        $short_detail .= '...';
                                    }
                                ?>
                                    <div class="bm-detail-excerpt">
                                        <p><?php echo esc_html($short_detail); ?></p>
                                    </div>

                                    <div class="bm-detail-full" style="display: none;">
                                        <?php
                                        // Display formatted ACF detail
                                        echo wp_kses_post($member_detail);
                                        ?>
                                    </div>

                                    <?php if (mb_strlen($clean_detail) > $excerpt_length) : ?>
                                        <button class="bm-read-more-btn" aria-expanded="false">
                                            <span class="text-more">Read More</span>
                                            <span class="text-less" style="display:none;">Read Less</span>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-chevron-down icon-more" viewBox="0 0 16 16">
                                                <path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z" />
                                            </svg>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-chevron-up icon-less" viewBox="0 0 16 16" style="display:none;">
                                                <path fill-rule="evenodd" d="M7.646 4.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1-.708.708L8 5.707l-5.646 5.647a.5.5 0 0 1-.708-.708l6-6z" />
                                            </svg>
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
            <?php
                endwhile;
                wp_reset_postdata();
            else :
                echo '<p>' . esc_html__('No board members found.', 'textdomain') . '</p>';
            endif;
            ?>
        </div>
    </div>
</main>

<style>
    /* CSS for 2-column Board Members Layout */
    .board-members-header {
        text-align: center;
        margin-bottom: 4rem;
        padding-top: 2rem;
    }

    .board-members-list {
        display: flex;
        flex-direction: column;
        gap: 50px;
        padding: 20px 0;
        max-width: 1000px;
        /* Constrain width for better reading experience */
        margin: 0 auto 60px auto;
    }

    .board-member-row {
        display: flex;
        flex-direction: row;
        gap: 40px;
        background: #ffffff;
        border-radius: 16px;
        padding: 30px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        align-items: flex-start;
        /* Align to top */
    }

    .board-member-row:hover {
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
    }

    /* Left Column Styling */
    .bm-left-col {
        flex: 0 0 250px;
        /* Fixed width for the left column */
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
    }

    .bm-image-wrapper {
        width: 220px;
        height: 220px;
        border-radius: 20px;
        overflow: hidden;
        margin-bottom: 20px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
    }

    .bm-image-empty {
        background: #f0f0f0;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .bm-image-wrapper img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center top;
        /* Focus on face usually */
    }

    .bm-name {
        margin: 0 0 10px 0;
        font-size: 1.6rem;
        color: #4DB3F4;
        /* Light blue name similar to image */
        font-weight: 700;
    }

    .bm-email {
        font-size: 0.95rem;
    }

    .bm-email a {
        color: #666;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: color 0.2s;
    }

    .bm-email a:hover {
        color: #4DB3F4;
    }

    /* Right Column Styling */
    .bm-right-col {
        flex: 1;
        /* Take up remaining space */
        display: flex;
        flex-direction: column;
        padding-top: 10px;
        /* Slight offset to align text with image nicely */
    }

    .bm-content {
        display: flex;
        flex-direction: column;
    }

    .bm-detail-excerpt {
        color: #3b4252;
        font-size: 1.1rem;
        line-height: 1.7;
        margin-bottom: 0;
    }

    .bm-detail-full {
        color: #3b4252;
        font-size: 1.1rem;
        line-height: 1.7;
        margin-bottom: 0;
    }

    .bm-detail-full p {
        margin-bottom: 15px;
    }

    .bm-detail-excerpt p:last-child,
    .bm-detail-full p:last-child {
        margin-bottom: 0;
    }

    /* Typography inherited from theme mostly, but ensuring lists look okay in full detail */
    .bm-detail-full ul,
    .bm-detail-full ol {
        margin-left: 20px;
        margin-bottom: 15px;
    }

    .bm-read-more-btn {
        background: none;
        border: none;
        color: #4DB3F4;
        /* Match name color */
        padding: 0;
        font-weight: 600;
        font-size: 1rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: color 0.2s;
        margin-top: 20px;
        align-self: flex-start;
        /* Align button to the left */
    }

    .bm-read-more-btn:hover {
        color: #1a8ad8;
    }

    .bm-read-more-btn svg {
        transition: transform 0.2s;
    }

    /* Animation class for JS */
    .bm-animating {
        overflow: hidden;
    }

    /* Mobile Responsive */
    @media (max-width: 768px) {
        .board-member-row {
            flex-direction: column;
            align-items: center;
            padding: 25px 20px;
        }

        .bm-left-col {
            flex: none;
            width: 100%;
            margin-bottom: 20px;
        }

        .bm-right-col {
            padding-top: 0;
            text-align: center;
        }

        .bm-read-more-btn {
            align-self: center;
            /* Center button on mobile */
        }
    }
</style>

<script>
    jQuery(document).ready(function($) {
        $('.bm-read-more-btn').on('click', function(e) {
            e.preventDefault();

            var $btn = $(this);
            var $card = $btn.closest('.bm-content');
            var $excerpt = $card.find('.bm-detail-excerpt');
            var $fullDetail = $card.find('.bm-detail-full');
            var isExpanded = $btn.attr('aria-expanded') === 'true';

            if (!isExpanded) {
                // Expanding (Accordion open)
                $excerpt.slideUp(300);
                $fullDetail.slideDown(400);
                $btn.attr('aria-expanded', 'true');
                $btn.find('.text-more, .icon-more').hide();
                $btn.find('.text-less, .icon-less').show();
            } else {
                // Collapsing (Accordion close)
                $fullDetail.slideUp(300);
                $excerpt.slideDown(400);
                $btn.attr('aria-expanded', 'false');
                $btn.find('.text-less, .icon-less').hide();
                $btn.find('.text-more, .icon-more').show();
            }
        });
    });
</script>

<?php get_footer(); ?>