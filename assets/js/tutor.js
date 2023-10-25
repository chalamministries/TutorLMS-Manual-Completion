function markQuizComplete(ul) {
 
	var params = ul.split("-");
	
	console.log(params, params[0], params[1]);


	jQuery.ajax({
		type: 'POST',
		url: tcsg.ajax_url,
		dataType: "json",
		data: {
			action: 'tcsg_mark_quiz_complete',
			quiz_id: params[1],
			user_id: params[0],
			nonce: tcsg.security
		},
		success: function(response) {
			if(response.status == 1) {
				jQuery('.tcsg_tutor-course-topic-item-flex[data-quiz-id='+ params[1] +']').find('.tcsg_tutor_not_complete').empty().removeClass('tcsg_tutor_not_complete').addClass('tcsg_tutor_grade_icon').html('<strong class="tcsg_tutor_quiz_pass">PASS</strong> (100%)');
				friconix_update();
			}
		}
	});
}

function markAssignmentComplete(ul) {
 
	var params = ul.split("-");
	
	console.log(params, params[0], params[1]);

	jQuery.ajax({
		type: 'POST',
		url: tcsg.ajax_url,
		dataType: "json",
		data: {
			action: 'tcsg_mark_assignment_complete',
			assignment_id: params[1],
			user_id: params[0],
			nonce: tcsg.security
		},
		success: function(response) {
			if(response.status == 1) {
				jQuery('.tcsg_tutor-course-topic-item-flex[data-assignment-id='+ params[1] +']').find('.tcsg_tutor_not_complete').empty().removeClass('tcsg_tutor_not_complete').addClass('tcsg_tutor_completed_icon').html('<i class="fi-cwsuxl-check"></i>');
				friconix_update();
				
			}
		}
	});
}

function markLessonComplete(ul) {
 
	var params = ul.split("-");
	
	console.log(params, params[0], params[1]);

	jQuery.ajax({
		type: 'POST',
		url: tcsg.ajax_url,
		dataType: "json",
		data: {
			action: 'tcsg_mark_lesson_complete',
			lesson_id: params[1],
			user_id: params[0],
			nonce: tcsg.security
		},
		success: function(response) {
			if(response.status == 1) {
				jQuery('.tcsg_tutor-course-topic-item-flex[data-lesson-id='+ params[1] +']').find('.tcsg_tutor_not_complete').empty().removeClass('tcsg_tutor_not_complete').addClass('tcsg_tutor_completed_icon').html('<i class="fi-cwsuxl-check"></i>');
				friconix_update();
				
			}
		}
	});
}

function markCourseComplete(ul) {
 
	var params = ul.split("-");
	
	console.log(params, params[0], params[1]);
	
	var lessons = jQuery('.tcsg_tutor_enrolled_course[data-course-id='+ params[1] +']').find('.tcsg_tutor-course-topic-item-lesson');
	jQuery.each(lessons, function(i, lesson){
		var item = jQuery(lesson).find('.tcsg_tutor-course-topic-item-flex');
		var lesson_id = item.attr("data-lesson-id");
		if(item.has('.tcsg_tutor_not_complete')) {
			markLessonComplete(tcsg.userId + "-" + lesson_id);
		}
	});
	
	var assignments = jQuery('.tcsg_tutor_enrolled_course[data-course-id='+ params[1] +']').find('.tcsg_tutor-course-topic-item-assignment');
	jQuery.each(assignments, function(i, assignment){
		var item = jQuery(assignment).find('.tcsg_tutor-course-topic-item-flex');
		var assignment_id = item.attr("data-assignment-id");
		if(item.has('.tcsg_tutor_not_complete')) {
			markAssignmentComplete(tcsg.userId + "-" + assignment_id);
		}
	});
	
	var quizzes = jQuery('.tcsg_tutor_enrolled_course[data-course-id='+ params[1] +']').find('.tcsg_tutor-course-topic-item-quiz');
	jQuery.each(quizzes, function(i, quiz){
		var item = jQuery(quiz).find('.tcsg_tutor-course-topic-item-flex');
		var quiz_id = item.attr("data-quiz-id");
		if(item.has('.tcsg_tutor_not_complete')) {
			markQuizComplete(tcsg.userId + "-" + quiz_id);
		}
	});
	
	jQuery.ajax({
		type: 'POST',
		url: tcsg.ajax_url,
		dataType: "json",
		data: {
			action: 'tcsg_mark_course_complete',
			course_id: params[1],
			user_id: params[0],
			nonce: tcsg.security
		},
		success: function(response) {
			if(response.status == 1) {
				jQuery('.tcsg_tutor_enrolled_course[data-course-id='+ params[1] +']').find('.tcsg_tutor_enrolled_course_title .tcsg_tutor_not_complete').empty().removeClass('tcsg_tutor_not_complete').addClass('tcsg_tutor_completed_icon').html('<i class="fi-cwsuxl-check"></i>');
				friconix_update();
			}
		}
	});
}
