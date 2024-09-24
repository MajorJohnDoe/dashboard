<?php
namespace Dashboard\Taskboard;

class HistoryCalendar {
    private $currentDate;
    private $tasks;

    public function __construct($date = null) {
        $this->currentDate = $date ? new \DateTime($date) : new \DateTime();
        $this->currentDate->setTime(0, 0, 0); // Reset time to start of day
        $this->tasks = [];
    }

    public function setTasks($tasks) {
        $this->tasks = $tasks;
    }

    private function getTasksForDay($date) {
        $dateString = $date->format('Y-m-d');
        return isset($this->tasks[$dateString]) ? $this->tasks[$dateString] : [];
    }

    private function generateWeek() {
        $html = '';
        $weekStart = clone $this->currentDate;
        $weekStart->modify('monday this week');
        $weekEnd = clone $weekStart;
        $weekEnd->modify('+6 days');
    
        $html .= '<div class="week">';
        $html .= '<div class="week-number"></div>';
    
        for ($day = clone $weekStart; $day <= $weekEnd; $day->modify('+1 day')) {
            $dayTasks = $this->getTasksForDay($day);
            $isToday = $day->format('Y-m-d') === (new \DateTime())->format('Y-m-d');

            $html .= '<div data-date="' . $day->format('Y-m-d') . '" class="day' . ($isToday ? ' today' : '') . '">';
            $html .= '<ul class="tasks">';
            foreach ($dayTasks as $task) {
                $taskTitle = isset($task['task_title']) ? htmlspecialchars($task['task_title'], ENT_QUOTES, 'UTF-8') : 'Untitled Task';
    
                $taskLabels = '';
                if (isset($task['labels']) && is_array($task['labels']) && count($task['labels']) > 0) {
                    $formattedLabels = array_map(function($label) {
                        $fontColor = adjustHexColorBrightness(htmlspecialchars($label['label_color']), -155);
                        return '<div style="background-color: '.htmlspecialchars($label['label_color'], ENT_QUOTES, 'UTF-8').'; color: '.$fontColor.';">'.htmlspecialchars($label['label_name'], ENT_QUOTES, 'UTF-8').'</div>';
                    }, $task['labels']);
                
                    $taskLabels .= implode('', $formattedLabels);
                }
    
                $html .= '<li class="tm-task tm-card-lcolor open-modal-btn" style="font-size: 90%;"
                                data-id="'.$task['task_id'].'"
                                data-modal-target="#dialog-column-add-task"
                                hx-get="/task/dialog/edit/'.$task['column_id'].'/'.$task['task_id'].'"
                                hx-target="body" 
                                hx-swap="beforeend"
                            >
                            <div class="flex-table">
                                <div class="flex-row">
                                    <div class="flex-cell flex-cell-vcenter" style="padding: 0px;">
                                        <div class="title">' . $taskTitle . '</div>
                                        ' . (strlen($taskLabels) > 0 ? '<div class="labels">'.$taskLabels.'</div>' : '') . '
                                    </div>
                                </div>
                            </div>
                        </li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }
    
        $html .= '</div>';
        return $html;
    }

    public function printCalendar() {
        $html = '<div id="calendar">' . PHP_EOL;
        $html .= '    <section class="th">' . PHP_EOL;
        $html .= '        <span style="text-align: center;">week<br>' . $this->currentDate->format('W') . '</span>' . PHP_EOL;
        $html .= '        <span>Monday</span>' . PHP_EOL;
        $html .= '        <span>Tuesday</span>' . PHP_EOL;
        $html .= '        <span>Wednesday</span>' . PHP_EOL;
        $html .= '        <span>Thursday</span>' . PHP_EOL;
        $html .= '        <span>Friday</span>' . PHP_EOL;
        $html .= '        <span>Saturday</span>' . PHP_EOL;
        $html .= '        <span>Sunday</span>' . PHP_EOL;
        $html .= '    </section>' . PHP_EOL;
        $html .= $this->generateWeek();
        $html .= '</div>' . PHP_EOL;

        echo $html;
    }

    public function changeWeek($direction) {
        $this->currentDate->modify($direction == 'next' ? '+1 week' : '-1 week');
    }

    public function getDatesForWeekNumber($weekNumber, $year = null) {
        // If year is not provided, use the current year
        if ($year === null) {
            $year = date('Y');
        }

        // Create a DateTime object for the first day of the year
        $date = new \DateTime();
        $date->setISODate($year, $weekNumber);

        $weekDates = [];
        for ($day = 0; $day < 7; $day++) {
            $weekDates[] = $date->format('Y-m-d');
            $date->modify('+1 day');
        }

        return $weekDates;
    }
}

?>