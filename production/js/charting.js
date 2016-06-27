function showMentalities(){
    $.get("../actions.php?action=mentalities&cache="+new Date().getTime(), function(data){
        $('#avgmentality').highcharts({
            credits: false,
            chart: {
                type: 'area'
            },
            series: data,
            title: {
                text: 'Overall Mentality'
            },
            subtitle: {
                text: 'Average 24 hour period'
            },
            xAxis: {
                categories: ['00am', '1am', '2am', '3am', '4am', '5am', '6am', '7am', '8am', '9am', '10am', '11am', '12pm', '1pm', '2pm', '3pm', '4pm', '5pm', '6pm', '7pm', '8pm', '9pm', '10pm', '11pm', '00am'],
                tickmarkPlacement: 'on',
                title: {
                    enabled: false
                }
            },
            yAxis: {
                title: {
                    text: 'Volume (Num of tweets)'
                }
            },
            tooltip: {
                pointFormat: '<span style="color:{series.color}">{series.name}</span>: <b>{point.percentage:.1f}%</b> ({point.y:,.0f})<br/>',
                shared: true
            },
            plotOptions: {
                area: {
                    stacking: 'volume',
                    lineColor: '#ffffff',
                    lineWidth: 1,
                    marker: {
                        lineWidth: 1,
                        lineColor: '#ffffff'
                    }
                }
            }
        });
    });
}

function showGraph(){
    $.get("../actions.php?action=avgVol&cache="+new Date().getTime(), function(data){
        $('#tweetVolume').highcharts({
            credits: false,
            chart: {
                zoomType: ''
            },
            title: {
                text: 'Average Volume of (included) tweets per hour'
            },
            subtitle: {
                text: ''
            },
            xAxis: {
                type: 'datetime'
            },
            yAxis: {
                title: {
                    text: 'Volume (Number of tweets)'
                }
            },
            legend: {
                enabled: false
            },
            plotOptions: {
                area: {
                    fillColor: {
                        linearGradient: {
                            x1: 0,
                            y1: 0,
                            x2: 0,
                            y2: 1
                        },
                        stops: [
                            [0, Highcharts.getOptions().colors[0]],
                            [1, Highcharts.Color(Highcharts.getOptions().colors[0]).setOpacity(0).get('rgba')]
                        ]
                    },
                    marker: {
                        radius: 2
                    },
                    lineWidth: 1,
                    states: {
                        hover: {
                            lineWidth: 1
                        }
                    },
                    threshold: 0
                }
            },

            series: [{
                type: 'area',
                name: 'Tweets per day',
                data: data
            }]
        });
    });
}

function setSpeedometer(type, name, id){
    $.get("../actions.php?action=tpmMent&type="+type+"&cache=" + new Date().getTime(), function (data) {
        var total = data;
        $('#'+id).highcharts({
                credits: false,
                chart: {
                    type: 'gauge',
                    plotBackgroundColor: null,
                    plotBackgroundImage: null,
                    plotBorderWidth: 0,
                    plotShadow: false
                },

                title: {
                    text: name + ' ('+total+' tpm)'
                },

                pane: {
                    startAngle: -150,
                    endAngle: 150,
                    background: [{
                        backgroundColor: {
                            linearGradient: { x1: 0, y1: 0, x2: 0, y2: 1 },
                            stops: [
                                [0, '#FFF'],
                                [1, '#333']
                            ]
                        },
                        borderWidth: 0,
                        outerRadius: '109%'
                    }, {
                        backgroundColor: {
                            linearGradient: { x1: 0, y1: 0, x2: 0, y2: 1 },
                            stops: [
                                [0, '#333'],
                                [1, '#FFF']
                            ]
                        },
                        borderWidth: 1,
                        outerRadius: '107%'
                    }, {
                        // default background
                    }, {
                        backgroundColor: '#DDD',
                        borderWidth: 0,
                        outerRadius: '105%',
                        innerRadius: '103%'
                    }]
                },

                // the value axis
                yAxis: {
                    min: 0,
                    max: 300,

                    minorTickInterval: 'auto',
                    minorTickWidth: 1,
                    minorTickLength: 10,
                    minorTickPosition: 'inside',
                    minorTickColor: '#666',

                    tickPixelInterval: 30,
                    tickWidth: 2,
                    tickPosition: 'inside',
                    tickLength: 10,
                    tickColor: '#666',
                    labels: {
                        step: 2,
                        rotation: 'auto'
                    },
                    title: {
                        text: '/min'
                    },
                    plotBands: [{
                        from: 0,
                        to: 100,
                        color: '#55BF3B' // green
                    }, {
                        from: 100,
                        to: 200,
                        color: '#DDDF0D' // yellow
                    }, {
                        from: 200,
                        to: 300,
                        color: '#DF5353' // red
                    }]
                },

                series: [{
                    name: 'Tweets: ',
                    data: [0],
                    tooltip: {
                        valueSuffix: ' per min'
                    }
                }]

            },
            // Add some life
            function (chart) {


                var point = chart.series[0].points[0];
                if(total > 300){
                    total = 300;
                }
                point.update(parseInt(total));

                if (!chart.renderer.forExport) {
                    setInterval(function () {

                        $.get("../actions.php?action=tpmMent&type="+type+"&cache=" + new Date().getTime(), function (data) {
                            var point = chart.series[0].points[0];
                            point.update(parseInt(data));
                        });


                    }, 60*1000);
                }
            });

    });
}

function updateTopStats() {
    $('.updatable').html('<img src="loader.gif"/>');

    //Get json response
    $.get("../actions.php?action=totals&cache="+ new Date().getTime(), function(data){
        var response = JSON.parse(data);

        $('#totalTweets').html(response.totalTweets);
        $('#totalHashTags').html(response.totalHashes);
        $('#tpm').html(response.tpm);
        $('#totalTweeters').html(response.totalUsers);
        $('#lastRun').html(response.lastRun);

        setTimeout(function(){
            updateTopStats();
        }, 30000);
    });
}

function getTopHashTags(){
    $.get("../actions.php?action=hashtag&cache="+ new Date().getTime(), function(data){
        //Remove the timer
        $('#hashtags').html(data);
        setTimeout(function(){
            getTopHashTags();
        },30000);
    });
}

function getTwatters(){
    $.get("../actions.php?action=getTodaysTwatter&cache="+ new Date().getTime(), function(data){
        //Remove the timer
        $('#twatters').html(data);
        setTimeout(function(){
            getTwatters();
        },30000);
    });
}

function updateTweets() {
    //Initial load?
    var nextNum = $('#lastID').html();
    var limit = '2000';
    //Split out to relevant column
    $.get("../actions.php?action=tweets&limit="+limit+"&next="+nextNum+"&long=false&cache="+new Date().getTime(), function(data){

        var response = JSON.parse(data);
        $.each(response.tweets, function (i,v) {
            var type = v.mentality;
            if(v.twitterName != null){
                var tweet = formatTweet(v);
                if(type == null){
                    var tweetHashID = 'allTweets';
                }
                else{
                    var tweetHashID = type;
                }
                if (!$("#"+v.tweetId).length > 0){
                    $('#'+tweetHashID).prepend(tweet);
                    setTimeout(function(){
                        $('.tweets').removeClass('newTweet');
                    }, 20000);
                }
            }
        });

        //set the ID of the newest tweet
        $('#lastID').html(response.tweets.id);
        setTimeout(function(){
            updateTweets();
        }, 30000);
    });
}

function formatTweet(data) {
    var classAdd = '';
    if($('#lastID').html() != ''){
        var classAdd = ' newTweet';
    }

    var outputText =data.tweet;

    var tweet = '<li id="'+data.tweetId+'"><div class="block"><div class="block_content"> <h2 class="title"><span class="image" style="width:12%; float:left;"><a href="http://twitter.com/'+data.twitterName+'" target="_blank"><img onerror="imgError(this);" user-id="'+data.twitterName+'" class="dashboard-avatar" alt="'+data.twitterName+'" src="'+data.picture+'"></a></span></h2><span class="message"><div class="byline"><span>'+data.date+' </span> - <a href="http://twitter.com/'+data.twitterName+'" target="_blank">@'+data.twitterName+'</a></div> <p class="excerpt">'+outputText+'</p></span></div></div></li>';

     return tweet;
}

function getMentalityTweets(type){
    $.get("../actions.php?action=listTweets&type="+type+"&cache="+ new Date().getTime(), function(data){
        var response = JSON.parse(data);
        $.each(response.tweets, function (i,v) {
            if(v.twitterName != null){
                var tweet = formatTweet(v);
                $('#'+type).prepend(tweet);
            }
        });
    });
}

function getStatus(){
    $.get("../actions.php?action=getCurrent&cache="+new Date().getTime(), function(data) {

        $('#currentStats').html(data);
        getRandomHashTweet();

    });
}

function getRandomHashTweet(){
    var hash = $('#hashNow').html();

    $.get("../actions.php?action=getRandom&hash="+hash+"&cache="+new Date().getTime(), function(data) {

        $('#hashExample').html(data);
    });
}

function imgError(image){
    var userId = $(image).attr('user-id');

    $.get("../actions.php?action=updateImage&twitterId="+userId+"&cache="+new Date().getTime(), function(data) {
        $(this).attr("src", data);
    });
}


function getPercents(){
    $.get("../actions.php?action=percents&cache="+ new Date().getTime(), function(data){

        $.each(data.type, function(field, value) {
            $("#"+field+"_chart").attr("data-percent",value.percent);
            $("#"+field+"_total").html("(" +value.total+" tweets)");
        });

        $('.chart').easyPieChart({
            easing: 'easeOutElastic',
            delay: 3000,
            barColor: '#26B99A',
            trackColor: '#fff',
            scaleColor: false,
            lineWidth: 20,
            trackWidth: 16,
            lineCap: 'butt',
            onStep: function() {
                $.each(data.type, function(field, value) {
                    $("#"+field+"_chart").find('.percent').text(Math.round(value.percent));
                });
            }
        });

    });
}

function setDay(){
    $.get("../actions.php?action=getDay&cache=" + new Date().getTime(), function (data) {
        $('.day').html(data);
    });
}

function getPastHours(){
    Chart.defaults.global.legend = {
        enabled: false
    };

    $.get("../actions.php?action=hours&cache="+ new Date().getTime(), function(data){

        $.each(data, function(values, name) {
            new Chart(document.getElementById("canvas_line_"+name.name), {
                type: 'line',
                data: {
                    labels: ["0am", "1am", "2am","3am", "4am", "5am", "6am", "7am", "8am", "9am", "10am", "11am", "12pm", "1pm","2pm", "3pm","4pm", "5pm","6pm", "7pm", "8pm", "9pm", "10pm", "11pm", "0am"],
                    datasets: [{
                        label: "Total Tweets",
                        backgroundColor: "rgba(38, 185, 154, 0.31)",
                        borderColor: "rgba(38, 185, 154, 0.7)",
                        pointBorderColor: "rgba(38, 185, 154, 0.7)",
                        pointBackgroundColor: "rgba(38, 185, 154, 0.7)",
                        pointHoverBackgroundColor: "#fff",
                        pointHoverBorderColor: "rgba(220,220,220,1)",
                        pointBorderWidth: 1,
                        data: name.data
                    }]
                },
            });
        });

    });
}

function getTypeTotals() {
    $.get("../actions.php?action=totalCounts&cache=" + new Date().getTime(), function (data) {


        $.each(data.type.data, function(field, name) {
            $('#'+field+'_yesterday').html(name.yesterday);
            $('#'+field+'_lastweek').html(name.lastWeek);
            $('#'+field+'_total_over').html(name.total);
        });
    });
}

$(document).ready(function () {
   // getMarkers();
    getPercents();
    getPastHours();
    getTypeTotals();
    setDay();
    setInterval(getTypeTotals,80000);
    setInterval(getPercents, 80000);
    setInterval(getPastHours, 80000);
    setInterval(setDay, 80000);

    getStatus();
    setInterval(getStatus, 80000);
    setInterval(getRandomHashTweet, 10000 );
    updateTopStats();
    getTopHashTags();
    getTwatters();

    //listTweets

    setInterval(getMentalityTweets('positive'), 80000 );
    setInterval(getMentalityTweets('negative'), 80000 );
    setInterval(getMentalityTweets('alcohol'), 80000 );
    setInterval(getMentalityTweets('swear'), 80000 );
    setInterval(getMentalityTweets('weather'), 80000 );
    setInterval(getMentalityTweets('football'), 80000 );
    setInterval(getMentalityTweets('animals'), 80000 );
 //   showGraph();
    showMentalities();
});
