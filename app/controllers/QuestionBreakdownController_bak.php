<?php

class QuestionBreakdownControllerBak extends BaseController {

    public function teacher_page(User $teacher, Cycle $cycle, $mode=2) {
        $school = $teacher->school;

        $now = Carbon\Carbon::createFromTimestamp(time());
        $enddate = Carbon\Carbon::createFromFormat('d/m/Y H:i', $cycle->end_date.' 23:59');
        if( $enddate->gt($now)) {
            return Redirect::to("/user/view/$teacher->id")->with('error', 'Cycle still ongoing');
        }

        $data['page'] = 'quick_look';
        $data['subheader_bold'] = 'Detailed Question Breakdown';
        $data['subheader'] = ', '.$teacher->name;
        $data['headertext'] = 'Distribution of scores for each question';
        $data['header'] = 'Teacher\'s Question Breakdown for classes of '.$cycle->name;
        $data['cycle'] = $cycle;
        $data['teacher_id'] = $teacher->id;
        $data['additional_info'] = '
                <p>This report provides a breakdown of results for every survey question, for each Class you teach.</p>
                <p>The 25 questions are grouped under the five Australian Professional Standards for Teachers.  At the top of each standard, you can see the highest and lowest scoring Class, and their average scores across that standard, where 1=strongly disagree and 5=strongly agree.</p>
                <p>For each question, you can see the school\'s average score - displayed both in an orange circle, and as a orange dotted line on the bar graph "Average of each Class".  Your average score per question, across all Classes, is shown in a purple box.  The bar graph "Average of each Class" tells you each individual Class score per question. Hover your mouse over columns on the bar graph "Average of each Class", to see the numerical score of each Class.</p>
                <p>The "Distribution of scores" graph indicates the number of your students, across all your Classes, who responded at each point on the scale (from "Strongly Disagree" (1) through to "Strongly Agree" (5)). The numbers at the top of each column tell you the number of student responses at each level.</p>
                <p>Note that where a Class has fewer than five students, the data will not be revealed to protect the anonymity of the students (the relevant section will read "N/A").</p>
                ';

        $limedata = new LimeData();
        $school_survey_ids = array();
        $teacher_survey_ids = array();
        $class_names = array();

        foreach ($cycle->classes as $class) {
            $school_survey_ids[] = $class->pivot->limesurvey_id;
            if ($class->teacher_id == $teacher->id) {
                $teacher_survey_ids[$class->code] = $class->pivot->limesurvey_id;
                $class_names[$class->code] = $class->name;
            }
        }

        $responses = 0;
        foreach ($teacher_survey_ids as $survey_ids) {
            $responses += $limedata->count_survey_responses($survey_ids);
        }

        if ($responses < Utils::$responses_threshold) {
            return Redirect::to("/user/view/$teacher->id")->with('error', 'Report has not yet met survey response number threshold');
        }


        $questiondata = $limedata->get_survey_questions(reset($school_survey_ids), LimeData::QUESTION_INDEX_COUNT);
        $statistics = $limedata->get_surveys_statistics($teacher_survey_ids);

        $school_survey_statistics = $limedata->get_survey_question_average($school_survey_ids);
        $teacher_survey_statistics = $limedata->get_survey_question_average($teacher_survey_ids);

        $question_groups = array();
        for ($i = 0; $i < 5; $i++) {
            $question_groups['question_group'.($i+1)] = array();
            $question_groups['question_group'.($i+1)]['standard_number'] = ($i+1);
            $question_groups['question_group'.($i+1)]['standard'] = Utils::$standards[$i];

            $questions = array();
            for ($j = 0; $j < 5; $j++) {
                $question = array();
                $question['number'] = 'Q'.(($i * 5) + $j + 1);
                $question['text'] = $questiondata[($i * 5) + $j]->question;

                // average
                $question['average2_label'] = 'School';
                $question['average2_value'] = number_format($school_survey_statistics[($i * 5) + $j], 1);

                $question['average1_label'] = 'Teacher';
                $question['average1_value'] = number_format($teacher_survey_statistics[($i * 5) + $j], 1);

                $totalresponses = array_sum($statistics[($i * 5) + $j]);
                $question['total_responses'] = $totalresponses;

                $question['chart_html'] = $this->quicklook_draw_right_chart($statistics[($i * 5) + $j], $totalresponses);

                $questions[]= $question;
            }
            $question_groups['question_group'.($i+1)]['questions'] = $questions;
        }
        $data['question_groups'] = $question_groups;


        //Average of each class
        $data['question_content2'] = 'Average of each Class';
        $data['aggr_type'] = 'Class';

        $class_averages = array();

        foreach ($teacher_survey_ids as $class => $survey_ids) {
            $class_averages[$class] = $limedata->get_survey_question_average(array($survey_ids));
        }


        $chartdata = array();
        $chartdata['aggr_type'] = 'Class average';
        $chartdata['tooltips'] = $class_names;
        for ($i = 0; $i < 25; $i++) {
            $qnumber = 'Q'.($i+1);
            $chartdata[$qnumber] = array();
            $chartdata[$qnumber]['linevalue'] = number_format($school_survey_statistics[$i],1) * 1;

            foreach($class_averages as $class => $average) {
                $chartdata[$qnumber]['series'][$class] = number_format($average[$i],1) * 1;
            }
            foreach ($chartdata[$qnumber]['series'] as $class => $val) {
                if ($val == max($chartdata[$qnumber]['series'])) {
                    $chartdata[$qnumber]['color'][$class] = '#736699';
                } else {
                    $chartdata[$qnumber]['color'][$class] = '#B8B0CC';
                }
            }
        }

        $this->quicklook_draw_mid_chart($chartdata);

        $highest = array();
        $lowest = array();
        $totals = array();
        $count = 0;
        $standard = 0;

        foreach ($chartdata as $qnumber => $group) {
            if (in_array($qnumber, array('aggr_type', 'tooltips'))) {
                continue;
            }
            foreach ($group['series'] as $aggr => $val) {
                if ($val == 0) {
                    continue;
                }
                if (!empty($totals[$aggr])) {
                   $totals[$aggr] += $val;
                } else {
                    $totals[$aggr] = $val;
                }
            }
            $count += 1;
            if ($count == 5) {
                $standard += 1;
                $highest[$standard] = array(implode(', ', array_keys($totals, max($totals))), number_format((max($totals) / 5), 1));
                $lowest[$standard] = array(implode(', ', array_keys($totals, min($totals))), number_format((min($totals) / 5), 1));
                $count = 0;
                $totals = array();
            }
        }

        $data['high_standards'] = $highest;
        $data['low_standards'] = $lowest;

        return $this->get_view('report-question_breakdown', $data);

    }

    public function department_head_page(Department $department, Cycle $cycle, $mode=1) {

        $now = Carbon\Carbon::createFromTimestamp(time());
        $enddate = Carbon\Carbon::createFromFormat('d/m/Y H:i', $cycle->end_date.' 23:59');
        if( $enddate->gt($now)) {
            return Redirect::to("/department/view/$department->id")->with('error', 'Cycle still ongoing');
        }

        $data['page'] = 'quick_look';
        $data['subheader_bold'] = 'Detailed Question Breakdown';
        $data['subheader'] = ', '.$department->name;
        $data['headertext'] = 'Distribution of scores for each question, by Year Level';
        $data['header'] = 'Head of Department\'s Question Breakdown for '.$department->name;
        $data['cycle'] = $cycle;
        $data['additional_info'] = '
                <p>This report provides a breakdown of results for every survey question, for each Year level within your Department.</p>
                <p>The 25 questions are grouped under the five Australian Professional Standards for Teachers.  At the top of each Standard, you can see the highest and lowest scoring Year level within your Department, and their average scores across that Standard, where 1 = strongly disagree and 5 = strongly agree.</p>
                <p>For each question, you can see the school\'s average score - displayed both in a orange circle, and as a orange dotted line on the bar graph "Average of each Year level".  The Department\'s average score per question, across all Teachers and all Classes, is shown in a purple box.  The bar graph "Average of each Year level" tells you each individual Year level\'s score per question. Hover over columns on the bar graph "Average of each Year level", to see the numerical average score for each Year level.</p>
                <p>The "Distribution of scores" graph indicates the number of students who responded at each point on the scale (from "Strongly Disagree" (1) through to "Strongly Agree" (5)).   The numbers at the top of each column tell you the number of student responses at each level.</p>
                <p>Note that where a year level in your Department has fewer than three teachers, the data will not be revealed to protect the anonymity of the teachers (the relevant section will read "N/A").</p>
                ';
        $limedata = new LimeData();
        $yearlevels = array();
        $school_survey_ids = array();
        $department_surveys = array();

        foreach ($cycle->classes as $class) {
            $school_survey_ids[] = $class->pivot->limesurvey_id;
            if ($class->department_id == $department->id) {
                $department_surveys[$class->id] = $class->pivot->limesurvey_id;
                $yearlevels[$class->year_level]['teachers'][$class->teacher->name] = 1;
                $yearlevels[$class->year_level]['surveys'][$class->id] = $class->pivot->limesurvey_id;
            }
        }

        $teacher_count = 0;
        foreach ($yearlevels as $aggr => $classdata) {
            if (count($classdata['teachers']) < Utils::$teacher_threshold) {
                unset($yearlevels[$aggr]['surveys']);
            } else {
                $teacher_count += count($classdata['teachers']);
            }
        }

        $responses = 0;

        foreach ($department_surveys as $survey_id) {
            $responses += $limedata->count_survey_responses($survey_id);
        }

        if ($responses < Utils::$responses_threshold || $teacher_count < Utils::$teacher_threshold) {
            return Redirect::to("/department/view/$department->id")->with('error', 'Report has not yet met survey response number threshold');
        }

        $questiondata = $limedata->get_survey_questions(reset($school_survey_ids), LimeData::QUESTION_INDEX_COUNT);
        $statistics = $limedata->get_surveys_statistics($department_surveys);

        $school_survey_statistics = $limedata->get_survey_question_average($school_survey_ids);
        $department_survey_statistics = $limedata->get_survey_question_average($department_surveys);

        $question_groups = array();
        for ($i = 0; $i < 5; $i++) {
            $question_groups['question_group'.($i+1)] = array();
            $question_groups['question_group'.($i+1)]['standard_number'] = ($i+1);
            $question_groups['question_group'.($i+1)]['standard'] = Utils::$standards[$i];

            $questions = array();
            for ($j = 0; $j < 5; $j++) {
                $question = array();
                $question['number'] = 'Q'.(($i * 5) + $j + 1);
                $question['text'] = $questiondata[($i * 5) + $j]->question;

                // average
                $question['average2_label'] = 'School';
                $question['average2_value'] = number_format($school_survey_statistics[($i * 5) + $j], 1);

                $question['average1_label'] = 'Dept.';
                $question['average1_value'] = number_format($department_survey_statistics[($i * 5) + $j], 1);

                $totalresponses = array_sum($statistics[($i * 5) + $j]);
                $question['total_responses'] = $totalresponses;

                $question['chart_html'] = $this->quicklook_draw_right_chart($statistics[($i * 5) + $j], $totalresponses);

                $questions[]= $question;
            }
            $question_groups['question_group'.($i+1)]['questions'] = $questions;
        }
        $data['question_groups'] = $question_groups;


        //Average of year level
        $data['question_content2'] = 'Average of each Year Level in '.$department->name;
        $data['aggr_type'] = 'Year Level';

        $average_by_year = array();
        $statistics_by_year = array();
        foreach ($yearlevels as $year => $surveys) {
            if (!empty($surveys['surveys'])) {
                $average_by_year[$year] = $limedata->get_survey_question_average($surveys['surveys']);
            } else {
                $average_by_year[$year] = array();
            }
        }

        ksort($average_by_year);

        $chartdata = array();
        $chartdata['aggr_type'] = 'Year average';
        $chartdata['tooltips'] = array();

        for ($i = 0; $i < 25; $i++) {
            $qnumber = 'Q'.($i+1);
            $chartdata[$qnumber] = array();
            $chartdata[$qnumber]['linevalue'] = number_format($school_survey_statistics[$i],1);

            foreach($average_by_year as $year => $average) {
                if (empty($average)) {
                    $chartdata[$qnumber]['series']['Year '.$year] = 0;
                } else {
                    $chartdata[$qnumber]['series']['Year '.$year] = $average[$i];
                }

                $chartdata['tooltips']['Year '.$year] = 'Year '.$year;
            }
            foreach ($chartdata[$qnumber]['series'] as $year => $val) {
                if ($val == max($chartdata[$qnumber]['series'])) {
                    $chartdata[$qnumber]['color'][$year] = '#736699';
                } else {
                    $chartdata[$qnumber]['color'][$year] = '#B8B0CC';
                }
            }
        }

        $this->quicklook_draw_mid_chart($chartdata);

        $highest = array();
        $lowest = array();
        $totals = array();
        $count = 0;
        $standard = 0;

        foreach ($chartdata as $qnumber => $group) {
            if (in_array($qnumber, array('aggr_type', 'tooltips'))) {
                continue;
            }
            foreach ($group['series'] as $aggr => $val) {
                if ($val == 0) {
                    continue;
                }
                if (!empty($totals[$aggr])) {
                    $totals[$aggr] += $val;
                } else {
                    $totals[$aggr] = $val;
                }
            }
            $count += 1;
            if ($totals && $count == 5) {
                $standard += 1;
                $highest[$standard] = array(implode(', ', array_keys($totals, max($totals))), number_format((max($totals) / 5), 1));
                $lowest[$standard] = array(implode(', ', array_keys($totals, min($totals))), number_format((min($totals) / 5), 1));
                $count = 0;
                $totals = array();
            }
        }

        $data['high_standards'] = $highest;
        $data['low_standards'] = $lowest;

        return $this->get_view('report-question_breakdown', $data);
    }

    public function principal_page(User $teacher, Cycle $cycle, $mode=1) {

        $school = $teacher->school;

        $now = Carbon\Carbon::createFromTimestamp(time());
        $enddate = Carbon\Carbon::createFromFormat('d/m/Y H:i', $cycle->end_date.' 23:59');
        if( $enddate->gt($now)) {
            return Redirect::to("/school/view/$school->id")->with('error', 'Cycle still ongoing');
        }

        $data['page'] = 'quick_look';
        $data['subheader_bold'] = 'Detailed Question Breakdown';
        $data['display_mode'] = $mode == 2 ? 1 : 2;
        $data['display_mode_text'] = $mode == 2 ? 'Department' : 'Year Level';
        $data['subheader'] = ', '.$school->name;
        $data['headertext'] = 'Distribution of scores for each question by Department/Year Level';
        $data['header'] = 'Principal\'s Question Breakdown for '.$school->name;
        $data['cycle'] = $cycle;
        $data['teacher_id'] = $teacher->id;
        $data['additional_info'] = '
                <p>This report provides a breakdown of results for every survey question, either for each department or for each year-level.</p>
                <p>At the top of the page, select the button "Show Department Breakdown" or "Show Year Level Breakdown" to reveal results either by Department or by Year level, for every question.</p>
                <p>The 25 questions are grouped under the five Australian Professional Standards for Teachers.  At the top of each standard, you can see the highest and lowest scoring Departments/Year levels, and their average scores across that standard, where 1=strongly disagree and 5=strongly agree.</p>
                <p>For each question, you can see the school\'s average score - displayed both in a orange circle, and as a orange dotted line on the bar graph "Average of each Department/Year level".  Hover over columns on the bar graph "Average of each Department/Year level", to see the numerical score of each Department/Year level.</p>
                <p>The "Distribution of scores" graph indicates the number of students who responded at each point on the scale (from "Strongly Disagree" through to "Strongly Agree"). </p>
                <p>Note that where a Department or year level has fewer than three teachers, the data will not be revealed to protect the anonymity of the teachers.</p>
                ';

        $limedata = new LimeData();
        $school_survey_ids = array();
        $aggr_surveys = array();

        foreach ($cycle->classes as $class) {
            $school_survey_ids[] = $class->pivot->limesurvey_id;
            if ($mode == 1) {
                $data['aggr_type'] = 'Department';
                $data['question_content2'] = 'Average of each Department';
                $aggr_surveys[$class->department->name]['teachers'][$class->teacher->name] = 1;
                $aggr_surveys[$class->department->name]['surveys'][] = $class->pivot->limesurvey_id;
            } else {
                $data['aggr_type'] = 'Year Level';
                $data['question_content2'] = 'Average of each Year Level';
                $aggr_surveys[$class->year_level]['teachers'][$class->teacher->name] = 1;
                $aggr_surveys[$class->year_level]['surveys'][] = $class->pivot->limesurvey_id;
            }
        }

        $teacher_count = 0;
        foreach ($aggr_surveys as $aggr => $classdata) {
            if (count($classdata['teachers']) < Utils::$teacher_threshold) {
                unset($aggr_surveys[$aggr]['surveys']);
            } else {
                $teacher_count += count($classdata['teachers']);
            }
        }

        $responses = 0;

        foreach ($school_survey_ids as $survey_id) {
            $responses += $limedata->count_survey_responses($survey_id);
        }

        if ($responses < Utils::$responses_threshold || $teacher_count < Utils::$teacher_threshold) {
            return Redirect::to("/school/view/$school->id")->with('error', 'Report has not yet met survey response number threshold');
        }

        $questiondata = $limedata->get_survey_questions(reset($school_survey_ids), LimeData::QUESTION_INDEX_COUNT);
        $statistics = $limedata->get_surveys_statistics($school_survey_ids);

        $school_survey_statistics = $limedata->get_survey_question_average($school_survey_ids);

        $question_groups = array();
        for ($i = 0; $i < 5; $i++) {
            $question_groups['question_group'.($i+1)] = array();
            $question_groups['question_group'.($i+1)]['standard_number'] = ($i+1);
            $question_groups['question_group'.($i+1)]['standard'] = Utils::$standards[$i];

            $questions = array();
            for ($j = 0; $j < 5; $j++) {
                $question = array();
                $question['number'] = 'Q'.(($i * 5) + $j + 1);
                $question['text'] = $questiondata[($i * 5) + $j]->question;

                // average
                $question['average2_label'] = 'School';
                $question['average2_value'] = number_format($school_survey_statistics[($i * 5) + $j], 1);

                $totalresponses = array_sum($statistics[($i * 5) + $j]);
                $question['total_responses'] = $totalresponses;

                $question['chart_html'] = $this->quicklook_draw_right_chart($statistics[($i * 5) + $j], $totalresponses);

                $questions[]= $question;
            }
            $question_groups['question_group'.($i+1)]['questions'] = $questions;
        }
        $data['question_groups'] = $question_groups;

        if ($mode == 1) {
            //Average of each dept

            $department_averages = array();
            foreach ($aggr_surveys as $depts => $surveys) {
                if (!empty($surveys['surveys'])) {
                    $department_averages[$depts] = $limedata->get_survey_question_average($surveys['surveys']);
                } else {
                    $department_averages[$depts] = array();
                }
            }

            $chartdata = array();
            $chartdata['tooltips'] = array();

            $chartdata['aggr_type'] = 'Department average';
            for ($i = 0; $i < 25; $i++) {
                $qnumber = 'Q'.($i+1);
                $chartdata[$qnumber] = array();
                $chartdata[$qnumber]['linevalue'] = number_format($school_survey_statistics[$i],1);

                foreach($department_averages as $dept => $average) {
                    if (empty($average)) {
                        $chartdata[$qnumber]['series'][$dept] = 0;
                    } else {
                        $chartdata[$qnumber]['series'][$dept] = $average[$i];
                    }

                    $chartdata['tooltips'][$dept] = $dept;
                }
                foreach ($chartdata[$qnumber]['series'] as $dept => $val) {
                    if ($val == max($chartdata[$qnumber]['series'])) {
                        $chartdata[$qnumber]['color'][$dept] = '#736699';
                    } else {
                        $chartdata[$qnumber]['color'][$dept] = '#B8B0CC';
                    }
                }
            }
        }

        if ($mode == 2) {
            //Average of year level
            $average_by_year = array();
            $statistics_by_year = array();
            foreach ($aggr_surveys as $year => $surveys) {
                if (!empty($surveys['surveys'])) {
                    $average_by_year[$year] = $limedata->get_survey_question_average($surveys['surveys']);
                } else {
                    $average_by_year[$year] = array();
                }
            }

            ksort($average_by_year);

            $chartdata = array();
            $chartdata['aggr_type'] = 'Year average';
            $chartdata['tooltips'] = array();

            for ($i = 0; $i < 25; $i++) {
                $qnumber = 'Q'.($i+1);
                $chartdata[$qnumber] = array();
                $chartdata[$qnumber]['linevalue'] = number_format($school_survey_statistics[$i],1);

                foreach($average_by_year as $year => $average) {
                    if (empty($average)) {
                        $chartdata[$qnumber]['series']['Year '.$year] = 0;
                    } else {
                        $chartdata[$qnumber]['series']['Year '.$year] = $average[$i];
                    }
                    $chartdata['tooltips']['Year '.$year] = 'Year '.$year;
                }
                foreach ($chartdata[$qnumber]['series'] as $year => $val) {
                    if ($val == max($chartdata[$qnumber]['series'])) {
                        $chartdata[$qnumber]['color'][$year] = '#736699';
                    } else {
                        $chartdata[$qnumber]['color'][$year] = '#B8B0CC';
                    }
                }
            }
        }

        $this->quicklook_draw_mid_chart($chartdata);

        $highest = array();
        $lowest = array();
        $totals = array();
        $count = 0;
        $standard = 0;

        foreach ($chartdata as $qnumber => $group) {
            if (in_array($qnumber, array('aggr_type', 'tooltips'))) {
                continue;
            }
            foreach ($group['series'] as $aggr => $val) {
                if ($val == 0) {
                    continue;
                }
                if (!empty($totals[$aggr])) {
                   $totals[$aggr] += $val;
                } else {
                    $totals[$aggr] = $val;
                }
            }
            $count += 1;
            if ($count == 5) {
                $standard += 1;
                $highest[$standard] = array(implode(', ', array_keys($totals, max($totals))), number_format((max($totals) / 5), 1));
                $lowest[$standard] = array(implode(', ', array_keys($totals, min($totals))), number_format((min($totals) / 5), 1));
                $count = 0;
                $totals = array();
            }
        }

        $data['high_standards'] = $highest;
        $data['low_standards'] = $lowest;

        return $this->get_view('report-question_breakdown', $data);
    }

    /**
     * Draws the distribution chart for a Quick Look page
     * @param array $responses (score => number of responses)
     * @param int $total total responses
     * @return string html of chart
     */

    public function quicklook_draw_right_chart ($responses, $total, $negative = false) {
        $html = '<ul class="quicklook_dist_chart">';
        $left = 0;
        $max = max($responses);
        foreach ($responses as $response) {
            $height = floor(($response / $total) * 100);
            if ($response == $max) {
                $html .= "<li class=\"highest\" style=\"height:$height%;left:$left%\"><div class=\"dist_response\">$response</div></li>";
            } else {
                $html .= "<li style=\"height:$height%;left:$left%\"><div class=\"dist_response\">$response</div></li>";
            }

            $left += 20;
        }
        if ($negative) {

        } else {
            $html .= '</ul><div class="chart_dist_left">Strongly disagree</div><div class="chart_dist_right">Strongly agree</div>';
        }

        return $html;
    }

    /**
     * Draws the averages chart for a Quick Look page
     * @param unknown $average
     * @param unknown $total
     */
    public function quicklook_draw_mid_chart ($data) {
        $this->js_include('http://code.highcharts.com/highcharts.js');
        $this->js_include('http://code.highcharts.com/highcharts-more.js');
        $this->js_include('http://code.highcharts.com/modules/exporting.js');

        $json = $data;

        $this->js_call('draw_mid_chart', $json, '/javascript/qbreakdown.js');
        $this->js_call('dropdown_fix', '', '/javascript/dropdown_fix.js');
    }
}