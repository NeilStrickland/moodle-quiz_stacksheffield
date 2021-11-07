define([],function() {
    var timeline_viewer = {};

    timeline_viewer.attempt = { id : 0, steps : null, num_steps : 0, last_step : null };
    timeline_viewer.step = { t : 0, id : 0, fraction : 0 };

    timeline_viewer.pad = function(t) {
        return ((t < 10) ? '0' : '') + t;
    };

    timeline_viewer.time_string = function(t) {
        var t0 = t;
        var d = Math.floor(t0 / (24 * 60 * 60));
        t0 -= d * 24 * 3600;
        var h = Math.floor(t0 / (60 * 60));
        t0 -= h * 60 * 60;
        var m = Math.floor(t0 / 60);
        t0 -= m * 60;
        var s = t0;
        if (d) {
            return '' + d + ':' + this.pad(h) + ':' + this.pad(m) + ':' + this.pad(s) + 's';
        } else if (h) {
            return '' + h + ':' + this.pad(m) + ':' + this.pad(s) + 's';
        } else if (m) {
            return '' + m + ':' + this.pad(s) + 's';
        } else {
            return '' + s + 's';
        }
    };

    timeline_viewer.add_attempt = function(a0) {
        var a = Object.create(this.attempt);
        a.steps = [];
        for (var i = 1; i <= a0.length; i++) {
            var s0 = a0[i-1];
            var s = Object.create(this.step);
            s.i = i;
            s.t = s0[0];
            s.quiz_attempt_id = s0[1];
            s.slot = s0[2];
            s.sequence_number = s0[3];
            s.fraction = s0[4];
            s.url = 'https://aim-dev.shef.ac.uk' +
                '/moodle/mod/quiz/reviewquestion.php' +
                '?attempt=' + s.quiz_attempt_id +
                '&slot=' + s.slot +
                '&step=' + s.sequence_number;
            a.steps.push(s);
        }

        a.num_steps = a.steps.length;
        a.last_step = a.steps[a.steps.length - 1];
        a.i = this.attempts.length;

        a.full_seconds = a.last_step.t;
        a.hours = Math.floor(a.full_seconds / 3600);
        a.minutes = Math.floor((a.full_seconds - 3600 * a.hours)/60);
        a.seconds = a.full_seconds - 3600 * a.hours - 60 * a.minutes;
        if (a.hours) {
            a.duration_string =
                a.hours +
                ':' + (a.minutes < 10 ? '0' : '') + a.minutes  +
                ':' + (a.seconds < 10 ? '0' : '') + a.seconds;
        } else if (a.minutes) {
            a.duration_string =
                a.minutes  +
                ':' + (a.seconds < 10 ? '0' : '') + a.seconds;
        } else {
            a.duration_string = '' + a.seconds;
        }

        if (a.num_steps == 1) {
            a.msg = 'One attempt; total duration ' + this.time_string(a.last_step.t) + '.';
        } else {
            a.msg = '' + a.num_steps + ' attempts; total duration ' + this.time_string(a.last_step.t) + '.';
        }

        for (var i = 1; i <= a.num_steps; i++) {
            var s = a.steps[i-1];
            s.msg = 'Attempt ' + i + '/' + a.num_steps + '; ' +
                this.time_string(s.t) + ' after first view';
            if (i > 1) {
                s.msg += '; ' +
                    this.time_string(s.t - a.steps[0].t) +
                    ' after first attempt';
            }
            if (i > 2) {
                s.msg += '; ' +
                    this.time_string(s.t - a.steps[i-2].t) +
                    ' after previous attempt';
            }
            s.msg += '.';
        }

        this.attempts.push(a);

        return a;
    };

    timeline_viewer.create_attempt_dom = function(a) {
        var me = this;

        a.x = a.last_step.t * this.w;
        a.y = 2 * (a.i + 2) * this.h;
        a.bar = document.createElementNS('http://www.w3.org/2000/svg','line');
        a.bar.setAttributeNS(null,'x1',0);
        a.bar.setAttributeNS(null,'y1',a.y);
        a.bar.setAttributeNS(null,'x2',a.x);
        a.bar.setAttributeNS(null,'y2',a.y);
        a.bar.setAttributeNS(null,'stroke',this.stroke_colour);
        a.bar.setAttributeNS(null,'stroke-width',this.stroke_width);
        a.bar.onmouseover = function() {
            me.show_msg(a.msg);
            a.bar.setAttributeNS(null,'stroke','#000000');
        };

        a.bar.onmouseout  = function() {
            me.show_msg('');
            a.bar.setAttributeNS(null,'stroke',me.stroke_colour);
        };

        this.svg.appendChild(a.bar);

        for (var i in a.steps) {
            var s = a.steps[i];
            this.create_step_dom(a,s);
        }
    };

    timeline_viewer.create_step_dom = function(a,s) {
        var me = this;

        s.marker = document.createElementNS('http://www.w3.org/2000/svg','circle');
        s.marker.setAttributeNS(null,'cx',s.t * this.w);
        s.marker.setAttributeNS(null,'cy',a.y);
        s.marker.setAttributeNS(null,'r',this.marker_radius);
        if (s.fraction == 1) {
            s.inactive_colour = this.correct_colour;
            s.active_colour = this.active_correct_colour;
        } else if (s.fraction == 0) {
            s.inactive_colour = this.incorrect_colour;
            s.active_colour = this.active_incorrect_colour;
        } else {
            s.inactive_colour = this.iffy_colour;
            s.active_colour = this.active_iffy_colour;
        }
        s.marker.setAttributeNS(null,'stroke',s.inactive_colour);
        s.marker.setAttributeNS(null,'fill',s.inactive_colour);

        s.marker.onmouseover = function() {
            me.show_msg(s.msg);
            s.marker.setAttributeNS(null,'stroke',s.active_colour);
            s.marker.setAttributeNS(null,'fill',s.active_colour);
        };

        s.marker.onmouseout  = function() {
            me.show_msg('');
            s.marker.setAttributeNS(null,'stroke',s.inactive_colour);
            s.marker.setAttributeNS(null,'fill',s.inactive_colour);
        };

        s.marker.onclick = function() { window.open(s.url); };

        this.svg.appendChild(s.marker);
    };

    timeline_viewer.create_line = function(i,hh) {
        var me = this;
        var l = document.createElementNS('http://www.w3.org/2000/svg','line');
        l.setAttributeNS(null,'x1',this.w * i);
        l.setAttributeNS(null,'y1',0);
        l.setAttributeNS(null,'x2',this.w * i);
        l.setAttributeNS(null,'y2',hh);
        if (i % 600 == 0) {
            l.setAttributeNS(null,'stroke','#0000DD');
            l.setAttributeNS(null,'stroke-width',3);
        } else {
            l.setAttributeNS(null,'stroke','#0000DD');
            l.setAttributeNS(null,'stroke-width',1);
            l.setAttributeNS(null,'stroke-dasharray','2,2');
        }

        l.onmouseover = function() {
            me.show_msg(me.time_string(i));
        };

        l.onmouseout = function() {
            me.show_msg('');
        };

        this.minute_lines[i] = l;
        this.svg.appendChild(l);
    };

    timeline_viewer.init = function() {
        this.div     = document.getElementById('timelines_div');
        this.svg     = document.getElementById('timelines_svg');
        this.msg_div = document.getElementById('timelines_msg');

        this.h = 3;
        this.w = 1;
        this.t_max = 800;
        this.marker_radius = 3;
        this.stroke_width = 3;
        this.stroke_colour           = '#888888';
        this.correct_colour          = '#00DD00';
        this.iffy_colour             = '#FFA500';
        this.incorrect_colour        = '#DD0000';
        this.active_correct_colour   = '#006600';
        this.active_iffy_colour      = '#886600';
        this.active_incorrect_colour = '#660000';
        this.data = JSON.parse(this.div.dataset.timelines);
        this.n = this.data.length;

        this.attempts = [];

        for (var i in this.data) {
            var a = this.add_attempt(this.data[i]);
            this.create_attempt_dom(a);
        }

        var aa = this.attempts[this.attempts.length - 1];
        var ww = aa.x + 10;
        var hh = aa.y + 10;
        this.svg.width = ww;
        this.svg.height = hh;
        this.svg.style.width = ww + 'px';
        this.svg.style.height = hh + 'px';
        this.svg.viewBox = '0 0 ' + ww + ' ' + hh;

        this.minute_lines = {};

        for (var i = 60; i <= aa.last_step.t; i += 60) {
            this.create_line(i,hh);
        }
    };

    timeline_viewer.show_msg = function(s) {
        this.msg_div.innerHTML = s;
    };

    return timeline_viewer;
});
