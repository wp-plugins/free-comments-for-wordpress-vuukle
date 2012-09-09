(function(w, d){
	'use strict';
	var back_end_url = 'http://www.vuukle.com/api.asmx/upload_old_comments',
		pluginurl,
		// total_req = '?action=vkimport&count_comments=P&callback=total_comments',
		export_req = '?action=vkimport&send_comments=2&callback=export_comments',
		ajax = (function () {
            return {
                call: function (u, f, p) {
                    var x = window.XMLHttpRequest ? new XMLHttpRequest(): new ActiveXObject('Microsoft.XMLHTTP');
                    x.onreadystatechange = function () {
                        if (x.readyState == 4 && x.status == 200) {
                            if (typeof f === 'function') {
                                
                                f(x);
                            }
                        }
                    };
                    if (p) {
                        x.open('POST', u, true);
                        x.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
                        x.send(p);
                    } else {
                        x.open('GET', u, true);
                        x.send(null);
                    }
                }
            };
        }()),
		load = function(src, onload){ 
            var i = document.createElement('script');
            i.src = src;
           	i.async = true; 
            if(typeof onload === 'function') {
                i.onload = onload;
            }
    
            document.getElementsByTagName('body')[0].appendChild(i);
        },
        clean_comments = function(string) {
            return string.replace(/("|'|&)/ig,'`');
        },
		vk_export_start = function (pg_url) {
			pluginurl = pg_url;
			// '?action=vkimport&data=762' 
			// st
			
			load(pluginurl+export_req);
		},
		done = function(r){
			//console.log();
            d.getElementById('vk_import').innerHTML = 'Export has been done!';
		},
        
		export_comments =function(ready_comments){
			var send_text, i,j, 
            no_i = ready_comments.length, 
            no_j;
            //console.log(ready_comments);
			//w.rrc = ready_comments;
            for(i=0;i<no_i; i+=1) {
                no_j = ready_comments[i].data.length;
                for(j=0;j<no_j; j+=1) {
                    ready_comments[i].data[j].comment = clean_comments(ready_comments[i].data[j].comment);
                }
            }

            send_text = 'list='+JSON.stringify(ready_comments);
			ajax.call(back_end_url, done, send_text);
          //  console.log('send textkey: "value", ',send_text);

		};



	w.vk_load = load;
	w.vk_export_start = vk_export_start;  
	w.export_comments = export_comments;  
}(window, window.document));