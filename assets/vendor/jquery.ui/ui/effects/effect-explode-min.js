/*! jQuery UI - v1.12.1 - 2017-03-31
* http://jqueryui.com
* Copyright jQuery Foundation and other contributors; Licensed  */
!function(a){"function"==typeof define&&define.amd?define(["jquery","../version","../effect"],a):a(jQuery)}(function(a){return a.effects.define("explode","hide",function(b,c){function d(){t.push(this),t.length===l*m&&e()}function e(){n.css({visibility:"visible"}),a(t).remove(),c()}var f,g,h,i,j,k,l=b.pieces?Math.round(Math.sqrt(b.pieces)):3,m=l,n=a(this),o=b.mode,p="show"===o,q=n.show().css("visibility","hidden").offset(),r=Math.ceil(n.outerWidth()/m),s=Math.ceil(n.outerHeight()/l),t=[];for(f=0;f<l;f++)for(i=q.top+f*s,k=f-(l-1)/2,g=0;g<m;g++)h=q.left+g*r,j=g-(m-1)/2,n.clone().appendTo("body").wrap("<div></div>").css({position:"absolute",visibility:"visible",left:-g*r,top:-f*s}).parent().addClass("ui-effects-explode").css({position:"absolute",overflow:"hidden",width:r,height:s,left:h+(p?j*r:0),top:i+(p?k*s:0),opacity:p?0:1}).animate({left:h+(p?0:j*r),top:i+(p?0:k*s),opacity:p?1:0},b.duration||500,b.easing,d)})});