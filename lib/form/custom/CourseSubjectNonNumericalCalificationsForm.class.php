<?php

/**
 * CourseSubjectNonNumericalCalificationsForm
 *
 */
class CourseSubjectNonNumericalCalificationsForm extends sfFormPropel
{
  public function getModelName()
  {
    return 'CourseSubject';
  }

  public function configure()
  {
     sfContext::getInstance()->getConfiguration()->loadHelpers(array('Asset', 'Javascript'));
    $this->widgetSchema->setNameFormat('course_subject_non_numerical_califications[%s]');
    $this->validatorSchema->setOption("allow_extra_fields", true);
    $sf_formatter_revisited = new sfWidgetFormSchemaFormatterRevisited($this);
    $this->getWidgetSchema()->addFormFormatter('Revisited', $sf_formatter_revisited);
    $this->getWidgetSchema()->setFormFormatterName('Revisited');

    $c = new Criteria();
    $c->addjoin(CourseSubjectStudentPeer::STUDENT_ID, StudentPeer::ID, Criteria::INNER_JOIN);
    $c->add(CourseSubjectStudentPeer::COURSE_SUBJECT_ID, $this->getObject()->getId());
    $c->add(CourseSubjectStudentPeer::IS_NOT_AVERAGEABLE, false);

	$this->setWidget('set_all_course_subject_non_numerical_califications', new sfWidgetFormInputCheckbox());
    $this->setValidator('set_all_course_subject_non_numerical_califications', new sfValidatorBoolean());

    $this->setWidget('course_subject_id', new sfWidgetFormInputHidden());
    $this->setValidator('course_subject_id', new sfValidatorNumber());
    $this->setDefault('course_subject_id', $this->getObject()->getId());

    $this->setWidget("student_list", new sfWidgetFormPropelChoiceMany(array(
        'model' => 'Student',
        'add_empty' => false,
        'multiple' => true,
        'peer_method' => 'doSelectActive',
        'renderer_class' => 'csWidgetFormSelectDoubleList',
        'criteria' => $c
      )));

    $this->setValidator("student_list", new sfValidatorPropelChoiceMany(array(
        "model" => "Student",
        "required" => false,
      )));
  }

  protected function doSave($con = null)
  {
    $values = $this->getValues();
    $course_subject = CourseSubjectPeer::retrieveByPk($values['course_subject_id']);
    $course = $course_subject->getCourse();

    $con = (is_null($con)) ? $this->getConnection() : $con;

    try
    {
      $con->beginTransaction();
      
      if($values['set_all_course_subject_non_numerical_califications'] == 1){
		  //tomo todos los alumnos que No tienen seteado el flag is_not_averageable
		  $course_subject_students = $course->getIsAverageableCourseSubjectStudent();
		  
		  foreach($course_subject_students as $course_subject_student)
		  {
			$course_subject_student->setIsNotAverageable(true);
			$course_subject_student_marks = CourseSubjectStudentMarkPeer::retrieveByCourseSubjectStudent($course_subject_student->getId());

			foreach ($course_subject_student_marks as $mark)
			{
			  $mark->setIsClosed(true);
			  $mark->save($con);
			}
			$student_id = $course_subject_student->getStudentId();
			$student_approved_course_subject = new StudentApprovedCourseSubject();
			$student_approved_course_subject->setCourseSubject($course_subject);

			$student_approved_course_subject->setStudentId($student_id);
			$student_approved_course_subject->setSchoolYear($course_subject->getCareerSubjectSchoolYear()->getCareerSchoolYear()->getSchoolYear());

			$student_approved_career_subject = new StudentApprovedCareerSubject();
			$student_approved_career_subject->setStudentId($student_id);
			$student_approved_career_subject->setCareerSubject($course_subject->getCareerSubjectSchoolYear()->getCareerSubject());
			$student_approved_career_subject->setSchoolYear($course_subject->getCareerSubjectSchoolYear()->getCareerSchoolYear()->getSchoolYear());
			$student_approved_career_subject->save($con);

			$student_approved_course_subject->setStudentApprovedCareerSubject($student_approved_career_subject);
			$student_approved_course_subject->save($con);

			$course_subject_student->setStudentApprovedCourseSubject($student_approved_course_subject);
			$course_subject_student->save($con);
		  
		  }
	  
	  }
	  else
	  {
		  foreach ($values['student_list'] as $student_id)
		  {
			$course_subject_student = CourseSubjectStudentPeer::retrievebyCourseSubjectAndStudent($course_subject->getid(), $student_id);
			$course_subject_student->setIsNotAverageable(true);
			$course_subject_student_marks = CourseSubjectStudentMarkPeer::retrieveByCourseSubjectStudent($course_subject_student->getId());

			foreach ($course_subject_student_marks as $mark)
			{
			  $mark->setIsClosed(true);
			  $mark->save($con);
			}

			$student_approved_course_subject = new StudentApprovedCourseSubject();
			$student_approved_course_subject->setCourseSubject($course_subject);

			$student_approved_course_subject->setStudentId($student_id);
			$student_approved_course_subject->setSchoolYear($course_subject->getCareerSubjectSchoolYear()->getCareerSchoolYear()->getSchoolYear());

			$student_approved_career_subject = new StudentApprovedCareerSubject();
			$student_approved_career_subject->setStudentId($student_id);
			$student_approved_career_subject->setCareerSubject($course_subject->getCareerSubjectSchoolYear()->getCareerSubject());
			$student_approved_career_subject->setSchoolYear($course_subject->getCareerSubjectSchoolYear()->getCareerSchoolYear()->getSchoolYear());
			$student_approved_career_subject->save($con);

			$student_approved_course_subject->setStudentApprovedCareerSubject($student_approved_career_subject);
			$student_approved_course_subject->save($con);

			$course_subject_student->setStudentApprovedCourseSubject($student_approved_course_subject);
			$course_subject_student->save($con);
		  }
	  }

	  //chequeo si la cantidad de alumnos eximidos es igual a la cantidad de alumnos inscriptos en el curso y el curso esta abierto .
      if(count($course->getIsNotAverageableCourseSubjectStudent()) == $course->countStudents() && ! $course->getIsClosed())
      {
		  //cierro el curso.
		  $course->setIsClosed(true);
		  $course->save($con);
	  }

      $con->commit();
    }
    catch (Exception $e)
    {
      throw $e;
      $con->rollBack();
    }
  }

}
