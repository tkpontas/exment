<div class="box">
    <div class="box-header with-border">
        <span>
            @foreach($tools as $tool)
            {!! $tool !!}
            @endforeach
        </span>
    </div>
    <div id='calendar'></div>
</div>

<script>
    $(function () {
        var calendarEl = document.getElementById('calendar');

        var calendar = new FullCalendar.Calendar(calendarEl, {
            locale : "{{ $locale }}",
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            height: 'auto',
            fixedWeekCount: false,
            weekNumbers: true,
            navLinks: true,
            eventDidMount: function(info) {
                info.el.setAttribute('data-toggle', 'tooltip');
                info.el.setAttribute('data-original-title', info.event.title);
            },
            // call when reading event data
            eventDataTransform: function(event) { 
                if(event.allDayBetween) {
                    event.end = moment(event.end).add(1, 'days').format('YYYY-MM-DD');
                }
                
                // call handle event
                let jqEvent = $.Event('exment:calendar_bind');
                $(window).trigger(jqEvent, event);
                // If can get result, replace result.
                if(jqEvent && jqEvent.result){
                    event = jqEvent.result;
                }

                return event;
            },
            // like '14:30:00'
            eventTimeFormat: { 
                hour: '2-digit',
                minute: '2-digit'
            },
            eventDisplay: "block",
            // showing event size
            dayMaxEventRows: 5,
            // put your options and callbacks here
            events: {
                url: "{{ $data_url }}",
                extraParams: {
                    view: "{{ $view_id }}",
                },
            }
        });

        calendar.render();
      });
</script>
<style>

.fc-day-sun {
    .fc-col-header-cell-cushion,.fc-daygrid-day-number{
        color: red;
    }
}
.fc-day-sat {
    .fc-col-header-cell-cushion,.fc-daygrid-day-number{
        color: blue;
    }
}
.fc-day-grid-event:hover{
    opacity:0.8;
}
</style>