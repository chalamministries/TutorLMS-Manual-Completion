<?php
/*
Plugin Name: Tutor LMS Manual Completion
Plugin URI: https://github.com/chalamministries/TutorLMS-Manual-Completion
Description: Mark lessons, quizzes and courses as complete for users
Version: 1.5
Author: Steven Gauerke
Author URI: https://chalamministries.com
*/

require 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;


class Tutor_Completions {

    protected $V = 1.5;
    protected $USERID = null;
    protected $PLUGIN_NAME = "Tutor_Completions";
    

    public static function init() {

        $C = __CLASS__;
        new $C;
        
        $myUpdateChecker = PucFactory::buildUpdateChecker(
            'https://github.com/chalamministries/TutorLMS-Manual-Completion/',
            __FILE__,
            'tutor-manual-completion'
        );
        
        //Set the branch that contains the stable release.
        $myUpdateChecker->setBranch('main');

    }

    function __construct() {

        global $pagenow;

        if(!current_user_can('administrator')) return;
        
        add_action('wp_ajax_tcsg_mark_lesson_complete', array($this, 'mark_lesson_complete'));
        add_action('wp_ajax_tcsg_mark_course_complete', array($this, 'mark_course_complete'));
        add_action('wp_ajax_tcsg_mark_assignment_complete', array($this, 'mark_assignment_complete'));
        add_action('wp_ajax_tcsg_mark_quiz_complete', array($this, 'mark_quiz_complete'));

        if($pagenow == 'user-edit.php') {

            add_action('show_user_profile', [$this, 'showCourses']);
            add_action('edit_user_profile', [$this, 'showCourses']);
            add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
        }

    }

    function admin_enqueue_scripts() {

        wp_enqueue_style('tutor-completions-css', plugin_dir_url( __FILE__ ) . 'assets/css/tutor.css', false, $this->V);
        wp_enqueue_script('tutor-completions-js', plugin_dir_url( __FILE__ ) . 'assets/js/tutor.js', false, $this->V);
        wp_enqueue_script('tutor-completions_icons', 'https://friconix.com/cdn/friconix.js');
        
        $nonce = wp_create_nonce('tcsg-tutor');
        wp_localize_script('tutor-completions-js', 'tcsg', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'security' => $nonce,
            'userId' => $_GET['user_id'] 
        ));
    }

	function showCourses($U) {
        $this->USERID = $U->ID;
        
        $UserMeta = get_user_meta($U->ID);
        
        if(!isset($UserMeta['_is_tutor_student'])){
           return false;
        }
        
        $full_courses_list_array = array(
            'enrolled-courses'  => tutor_utils()->get_enrolled_courses_by_user($this->USERID, array( 'private', 'publish' ) ),
            'completed-courses' => tutor_utils()->get_completed_courses_ids_by_user($this->USERID),
        );
        // echo '<pre>';
        //print_r($full_courses_list_array['completed-courses']);
        //echo '</pre>';
		?>

        <h2>Enrolled Courses</h2>

    
		<?php
        foreach($full_courses_list_array['enrolled-courses']->posts AS $enrolledCourse) {
            $completed_icon = '<span class="tcsg_tutor_completed_icon"><i class="fi-cwsuxl-check"></i></span>';
            if(in_array($enrolledCourse->ID, $full_courses_list_array['completed-courses'])) {
                $course_complete_icon = $completed_icon;
            } else {
                $course_complete_icon = "";
            }
            
            echo '<div class="tcsg_tutor_enrolled_course" data-course-id="'. $enrolledCourse->ID .'">';
            echo '<h3 class="tcsg_tutor_enrolled_course_title">' . $enrolledCourse->post_title;
            if($course_complete_icon != "") {
                echo $course_complete_icon;
            } else {
                echo '<span class="tcsg_tutor_not_complete"><a href="javascript: void(0)" onclick="markCourseComplete(\''. $this->USERID . '-' . $enrolledCourse->ID .'\')" >(mark complete)</a></span>';
            } 
            echo '</h3>';
            echo $this->getTopicList($enrolledCourse->ID, $this->USERID);
            echo '</div>';
        }

	}

    function getTopicList($course_id, $user_id) {
        $topics = tutor_utils()->get_topics( $course_id );

        if ( $topics->have_posts() ) {
        
            // Loop through topics.
            while ( $topics->have_posts() ) {
                $topics->the_post();
                $topic_id        = get_the_ID();
                $topic_summery   = get_the_content();
                $total_contents  = $this->count_completed_contents_by_topic( $topic_id );
                $lessons         = tutor_utils()->get_course_contents_by_topic( get_the_ID(), -1 );
                $is_topic_active = false;
                
                ?>
                <div class="tcsg_tutor-course-topic tcsg_tutor-course-topic-<?php echo esc_attr( $topic_id ); ?>">
                    <div class="tcsg_tutor-accordion-item-header" tutor-course-single-topic-toggler>
                        
                        <div class="tcsg_tutor-course-topic-title">
                            <?php the_title(); ?>
                        </div>

                        <?php if ( isset( $total_contents['contents'] ) && $total_contents['contents'] > 0 ) : ?>
                            <div class="tcsg_tutor-course-topic-summary">
                                <?php echo esc_html( isset( $total_contents['completed'] ) ? $total_contents['completed'] : 0 ); ?>/<?php echo esc_html( isset( $total_contents['contents'] ) ? $total_contents['contents'] : 0 ); ?>
                            </div>
                        <?php endif; ?>

                    </div>
        
                    <div class="tcsg_tutor-accordion-item-body <?php echo $is_topic_active ? '' : 'tcsg_tutor-display-none'; ?>">
                        <?php
                        do_action( 'tutor/lesson_list/before/topic', $topic_id );
        
                        // Loop through lesson, quiz, assignment, zoom lesson.
                        while ( $lessons->have_posts() ) {
                            $lessons->the_post();
                            $lesson_post_type = get_post_type();
                            
                            $post = $lessons->post;
                            
                            if ( 'tutor_quiz' === $lesson_post_type ) {
                                $quiz = $post;
                                ?>
                                <div class="tcsg_tutor-course-topic-item tcsg_tutor-course-topic-item-quiz">
                                    <div class="tcsg_tutor-course-topic-item-flex" data-quiz-id="<?php echo esc_attr( $quiz->ID ); ?>">

                                            <span class="tcsg_tutor-course-topic-item-icon tutor-icon-quiz-o" area-hidden="true"></span>
                                            <span class="tcsg_tutor-course-topic-item-title tutor-fs-7 tutor-fw-medium">
                                                <?php echo esc_html( $quiz->post_title ); ?>
                                            </span>

                                            <?php
                                            
                                            $last_attempt = $this->get_quiz_attempts( $this->USERID, $quiz->ID )[0];
                                            $attempt_info = unserialize( $last_attempt->attempt_info );
                                            $passing = $attempt_info['passing_grade'];
                                            
                                            if($last_attempt->total_marks > 0) {
                                                $grade = (100 * $last_attempt->earned_marks) / $last_attempt->total_marks;
                                            } else {
                                                $grade = 0;
                                            }
                                            
                                            $attempt_ended = is_object( $last_attempt ) && ( 'attempt_ended' === ( $last_attempt->attempt_status ) || $last_attempt->is_manually_reviewed ) ? true : false;
                                            
                                            if($attempt_ended) {
                                                echo $grade >= $passing ? '<span class="tcsg_tutor_grade_icon"><strong class="tcsg_tutor_quiz_pass">PASS</strong> ('. $grade .'%)</span>' : '<span class="tcsg_tutor_grade_icon"><strong class="tcsg_tutor_quiz_fail">FAIL</strong> ('. $grade .'%)</span> ';
                                            } else {
                                                echo '<span class="tcsg_tutor_not_complete"><a href="javascript: void(0)" onclick="markQuizComplete(\''. $this->USERID . '-' . $quiz->ID .'\')" >(mark complete)</a></span>';
                                            }
                                            ?>
                                    </div>
                                </div>
                            <?php } elseif ( 'tutor_assignments' === $lesson_post_type ) { ?>
                                <div class="tcsg_tutor-course-topic-item tcsg_tutor-course-topic-item-assignment">
                                    <div class="tcsg_tutor-course-topic-item-flex" data-assignment-id="<?php echo esc_attr( $post->ID ); ?>">
                        
                                            <span class="tcsg_tutor-course-topic-item-icon tutor-icon-assignment" area-hidden="true"></span>
                                            <span class="tcsg_tutor-course-topic-item-title tutor-fs-7 tutor-fw-medium">
                                                <?php echo esc_html( $post->post_title ); ?>
                                            </span>
   
                                            <?php
                                                $assignment_submitted = tutor_utils()->is_assignment_submitted( $post->ID, $this->USERID);
                                                
                                                echo $assignment_submitted ? '<span class="tcsg_tutor_completed_icon"><i class="fi-cwsuxl-check"></i></span>' : '<span class="tcsg_tutor_not_complete"><a href="javascript: void(0)" onclick="markAssignmentComplete(\''. $this->USERID . '-' . $post->ID .'\')" >(mark complete)</a></span>';
                                            ?>

                                    </div>
                                </div>
                            <?php } elseif ( 'tutor_zoom_meeting' === $lesson_post_type ) { ?>
                                <div class="tutor-course-topic-item tutor-course-topic-item-zoom<?php echo esc_attr( ( get_the_ID() == $currentPost->ID ) ? ' is-active' : '' ); ?>">
                                    <a href="<?php echo $show_permalink ? esc_url( get_permalink( $post->ID ) ) : '#'; ?>">
                                        <div class="tutor-d-flex tutor-mr-32">
                                            <span class="tutor-course-topic-item-icon tutor-icon-brand-zoom-o tutor-mr-8 tutor-mt-2" area-hidden="true"></span>
                                            <span class="tutor-course-topic-item-title tutor-fs-7 tutor-fw-medium">
                                                <?php echo esc_html( $post->post_title ); ?>
                                            </span>
                                        </div>
                                        <div class="tutor-d-flex tutor-ml-auto tutor-flex-shrink-0">
                                            <?php if ( $show_permalink ) : ?>
                                                <?php do_action( 'tutor/zoom/right_icon_area', $post->ID, $lock_icon ); ?>
                                            <?php else : ?>
                                                <i class="tutor-icon-lock-line tutor-fs-7 tutor-color-muted tutor-mr-4" area-hidden="true"></i>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                </div>
                            <?php } elseif ( 'tutor-google-meet' === $lesson_post_type) { ?>
                                <div class="tutor-course-topic-item tutor-course-topic-item-zoom<?php echo esc_attr( get_the_ID() == $currentPost->ID ? ' is-active' : '' ); ?>">
                                    <a href="<?php echo $show_permalink ? esc_url( get_permalink( $post->ID ) ) : '#'; ?>">
                                        <div class="tutor-d-flex tutor-mr-32">
                                            <span class="tutor-course-topic-item-icon tutor-icon-brand-google-meet tutor-mr-8 tutor-mt-2" area-hidden="true"></span>
                                            <span class="tutor-course-topic-item-title tutor-fs-7 tutor-fw-medium">
                                                <?php echo esc_html( $post->post_title ); ?>
                                            </span>
                                        </div>
                                        <div class="tutor-d-flex tutor-ml-auto tutor-flex-shrink-0">
                                            <?php if ( $show_permalink ) : ?>
                                                <?php do_action( 'tutor/google_meet/right_icon_area', $post->ID, false ); ?>
                                            <?php else : ?>
                                                <i class="tutor-icon-lock-line tutor-fs-7 tutor-color-muted tutor-mr-4" area-hidden="true"></i>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                </div>
                            <?php } else { ?>
                                
                                <?php
                                $video     = tutor_utils()->get_video_info();
                                $play_time = false;
                                if ( $video ) {
                                    $play_time = $video->playtime;
                                }
                                $is_completed_lesson = $this->is_completed_lesson($post->ID, $this->USERID);
                                ?>
                                <div class="tcsg_tutor-course-topic-item tcsg_tutor-course-topic-item-lesson">
                                    <div class="tcsg_tutor-course-topic-item-flex" data-lesson-id="<?php the_ID(); ?>">
                            
                                            <?php
                                            $tutor_lesson_type_icon = $play_time ? 'brand-youtube-bold' : 'document-text';
                                            $markup                 = '<span class="tcsg_tutor-course-topic-item-icon tutor-icon-' . $tutor_lesson_type_icon . '" area-hidden="true"></span>';
                                            echo wp_kses(
                                                $markup,
                                                array(
                                                    'span' => array(
                                                        'class' => true,
                                                        'area-hidden' => true,
                                                    ),
                                                )
                                            );
                                            ?>
                                            <span class="tcsg_tutor-course-topic-item-title tutor-fs-7 tutor-fw-medium">
                                                <?php the_title(); ?>
                                            </span>

                                           <?php
        
                                            echo $is_completed_lesson ? '<span class="tcsg_tutor_completed_icon"><i class="fi-cwsuxl-check"></i></span>' : '<span class="tcsg_tutor_not_complete"><a href="javascript: void(0)" onclick="markLessonComplete(\''. $this->USERID . '-' . $post->ID .'\')" >(mark complete)</a></span>';
        
                                            ?>
                                
                                    </div>
                                </div>
                                <?php
                            }
                        }
                        $lessons->reset_postdata();
                        do_action( 'tutor/lesson_list/after/topic', $topic_id );
                        ?>
                    </div>
                </div>
                <?php
            }
            $topics->reset_postdata();
            wp_reset_postdata();
        }
    }
    
    public function get_quiz_attempts( $user_id = 0, $quiz_id ) {
        global $wpdb;
    
        $attempts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 	*
            FROM 		{$wpdb->prefix}tutor_quiz_attempts
            WHERE 		user_id = %d
            AND       quiz_id = %d
            ORDER BY 	attempt_id DESC
            ",
                $user_id,
                $quiz_id
            )
        );
    
        if ( is_array( $attempts ) && count( $attempts ) ) {
            return $attempts;
        }
    
        return false;
    }
    
    public function is_completed_lesson( $lesson_id = 0, $user_id = 0 ) {
        $lesson_id    = tutor_utils()->get_post_id( $lesson_id );
        $user_id      = tutor_utils()->get_user_id( $user_id );
        $is_completed = get_user_meta( $user_id, '_tutor_completed_lesson_id_' . $lesson_id, true );
    
        if ( $is_completed ) {
            return $is_completed;
        }
    
        return false;
    }
    
    function mark_lesson_complete() {

        // Checking nonce.
        if ( !wp_verify_nonce($_POST['nonce'] , 'tcsg-tutor') ) {
            wp_send_json_error('Invalid nonce.');
        }
    
        $user_id = sanitize_text_field($_POST['user_id']);
    
        if ( ! $user_id ) {
            wp_send_json_error('Invalid User ID.');
            return;
        }
    
        $lesson_id = sanitize_text_field($_POST['lesson_id']);
    
        if ( ! $lesson_id ) {
            wp_send_json_error('Invalid Lesson ID.');
            return;
        }
    
        // $validated = apply_filters( 'tutor_validate_lesson_complete', true, $user_id, $lesson_id );
        // if ( ! $validated ) {
        //     wp_send_json_error('Validation failed');
        //     return;
        // }
        
        update_user_meta( $user_id, '_tutor_completed_lesson_id_' . $lesson_id, tutor_time() );
    
        echo json_encode(array("status" => 1));
        wp_die();
    }
    
    function mark_course_complete() {
        // Checking nonce.
        if ( !wp_verify_nonce($_POST['nonce'] , 'tcsg-tutor') ) {
            wp_send_json_error('Invalid nonce.');
        }
        
        $user_id = sanitize_text_field($_POST['user_id']);
        $course_id = sanitize_text_field($_POST['course_id']);
        
        if ( ! $course_id || ! $user_id ) {
            wp_send_json_error('Invalid parameters.');
            return false;
        }

        /**
         * Marking course completed at Comment.
         */
        global $wpdb;
    
        $date = date( 'Y-m-d H:i:s', tutor_time() );
    
        // Making sure that, hash is unique.
        do {
            $hash     = substr( md5( wp_generate_password( 32 ) . $date . $course_id . $user_id ), 0, 16 );
            $has_hash = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(comment_ID) from {$wpdb->comments}
                    WHERE comment_agent = 'TutorLMSPlugin' AND comment_type = 'course_completed' AND comment_content = %s ",
                    $hash
                )
            );
    
        } while ( $has_hash > 0 );
    
        $data = array(
            'comment_post_ID'  => $course_id,
            'comment_author'   => $user_id,
            'comment_date'     => $date,
            'comment_date_gmt' => get_gmt_from_date( $date ),
            'comment_content'  => $hash, // Identification Hash.
            'comment_approved' => 'approved',
            'comment_agent'    => 'TutorLMSPlugin',
            'comment_type'     => 'course_completed',
            'user_id'          => $user_id,
        );
    
        $wpdb->insert( $wpdb->comments, $data );
    
        echo json_encode(array("status" => 1, "arr" => $data));
        wp_die();
    }
    
    function mark_assignment_complete() {
        global $wpdb;
        // Checking nonce.
        if ( !wp_verify_nonce($_POST['nonce'] , 'tcsg-tutor') ) {
            wp_send_json_error('Invalid nonce.');
        }
        
        $user_id = sanitize_text_field($_POST['user_id']);
        $assignment_id = sanitize_text_field($_POST['assignment_id']);
        
        if ( ! $assignment_id || ! $user_id ) {
            wp_send_json_error('Invalid parameters.');
            return false;
        }

        $user          = get_userdata( $user_id );
        $date          = date( 'Y-m-d H:i:s' );
        
        $is_running_submit = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(comment_ID) FROM {$wpdb->comments}
            WHERE comment_type = 'tutor_assignment'
            AND user_id = %d
            AND comment_post_ID = %d ",
                $user_id,
                $assignment_id
            )
        );

        $course_id = tutor_utils()->get_course_id_by( 'assignment', $assignment_id );
        
        $data = apply_filters(
            'tutor_assignment_start_submitting_data',
            array(
                'comment_post_ID'  => $assignment_id,
                'comment_author'   => $user->user_login,
                'comment_date'     => $date, // Submit Finished
                'comment_date_gmt' => $date, // Submit Started
                'comment_approved' => 'submitted', // submitting, submitted
                'comment_agent'    => 'TutorLMSPlugin',
                'comment_type'     => 'tutor_assignment',
                'comment_parent'   => $course_id,
                'user_id'          => $user_id,
            )
        );
        
        $wpdb->insert( $wpdb->comments, $data );
    
        echo json_encode(array("status" => 1, "arr" => $data));
        wp_die();
    }
    
    function mark_quiz_complete() {
        global $wpdb;
        // Checking nonce.
        if ( !wp_verify_nonce($_POST['nonce'] , 'tcsg-tutor') ) {
            wp_send_json_error('Invalid nonce.');
        }
        
        $user_id = sanitize_text_field($_POST['user_id']);
        $quiz_id = sanitize_text_field($_POST['quiz_id']);
        
        if ( ! $quiz_id || ! $user_id ) {
            wp_send_json_error('Invalid parameters.');
            return false;
        }
        
        $course_id = tutor_utils()->get_course_id_by( 'quiz', $quiz_id );
        
        $date = date( 'Y-m-d H:i:s', tutor_time() ); //phpcs:ignore
        
        $tutor_quiz_option = (array) maybe_unserialize( get_post_meta( $quiz_id, 'tutor_quiz_option', true ) );
        $attempts_allowed  = tutor_utils()->get_quiz_option( $quiz_id, 'attempts_allowed', 0 );
        
        $time_limit         = tutor_utils()->get_quiz_option( $quiz_id, 'time_limit.time_value' );
        $time_limit_seconds = 0;
        $time_type          = 'seconds';
        if ( $time_limit ) {
            $time_type = tutor_utils()->get_quiz_option( $quiz_id, 'time_limit.time_type' );
        
            switch ( $time_type ) {
                case 'seconds':
                    $time_limit_seconds = $time_limit;
                    break;
                case 'minutes':
                    $time_limit_seconds = $time_limit * 60;
                    break;
                case 'hours':
                    $time_limit_seconds = $time_limit * 60 * 60;
                    break;
                case 'days':
                    $time_limit_seconds = $time_limit * 60 * 60 * 24;
                    break;
                case 'weeks':
                    $time_limit_seconds = $time_limit * 60 * 60 * 24 * 7;
                    break;
            }
        }
        
        $max_question_allowed                                  = tutor_utils()->max_questions_for_take_quiz( $quiz_id );
        $tutor_quiz_option['time_limit']['time_limit_seconds'] = $time_limit_seconds;
        
        $attempt_data = array(
            'course_id'                => $course_id,
            'quiz_id'                  => $quiz_id,
            'user_id'                  => $user_id,
            'total_questions'          => $max_question_allowed,
            'total_answered_questions' => $max_question_allowed,
            'earned_marks'             => $max_question_allowed,
            'total_marks'             => $max_question_allowed,
            'attempt_info'             => maybe_serialize( $tutor_quiz_option ),
            'attempt_ip'               => tutor_utils()->get_ip(),
            'attempt_started_at'       => $date,
            'attempt_status'           => 'attempt_ended',
            'attempt_ended_at'         => date( 'Y-m-d H:i:s', tutor_time() ),
        );

        $wpdb->insert( $wpdb->prefix . 'tutor_quiz_attempts', $attempt_data );
    
        echo json_encode(array("status" => 1, "arr" => $attempt_data));
        wp_die();
    }
    
    public function count_completed_contents_by_topic( int $topic_id ): array {
        $topic_id  = sanitize_text_field( $topic_id );
        $contents  = tutor_utils()->get_contents_by_topic( $topic_id );
        $user_id   = $this->USERID;
        $completed = 0;
    
        $lesson_post_type      = 'lesson';
        $quiz_post_type        = 'tutor_quiz';
        $assignment_post_type  = 'tutor_assignments';
        $zoom_lesson_post_type = 'tutor_zoom_meeting';
        $google_meet_post_type = 'tutor-google-meet';
    
        if ( $contents ) {
            foreach ( $contents as $content ) {
                switch ( $content->post_type ) {
                    case $lesson_post_type:
                        $is_lesson_completed = tutor_utils()->is_completed_lesson( $content->ID, $user_id );
                        if ( $is_lesson_completed ) {
                            $completed++;
                        }
                        break;
                    case $quiz_post_type:
                        $has_attempt = tutor_utils()->has_attempted_quiz( $user_id, $content->ID );
                        if ( $has_attempt ) {
                            $completed++;
                        }
                        break;
                    case $assignment_post_type:
                        $is_assignment_completed = tutor_utils()->is_assignment_submitted( $content->ID, $user_id );
                        if ( $is_assignment_completed ) {
                            $completed++;
                        }
                        break;
                    case $zoom_lesson_post_type:
                        if ( \class_exists( '\TUTOR_ZOOM\Zoom' ) ) {
                            $is_zoom_lesson_completed = \TUTOR_ZOOM\Zoom::is_zoom_lesson_done( '', $content->ID, $user_id );
                            if ( $is_zoom_lesson_completed ) {
                                $completed++;
                            }
                        }
                        break;
                    case $google_meet_post_type:
                        if ( \class_exists( '\TutorPro\GoogleMeet\Frontend\Frontend' ) ) {
                            if ( \TutorPro\GoogleMeet\Validator\Validator::is_addon_enabled() ) {
                                $is_completed = \TutorPro\GoogleMeet\Frontend\Frontend::is_lesson_completed( false, $content->ID, $user_id );
                                if ( $is_completed ) {
                                    $completed++;
                                }
                            }
                        }
                        break;
                    default:
                        break;
                }
            }
        }
        return array(
            'contents'  => is_array( $contents ) ? count( $contents ) : 0,
            'completed' => $completed,
        );
    }
   
}

if(method_exists('Tutor_Completions', 'init')) add_action('plugins_loaded', ['Tutor_Completions', 'init']);