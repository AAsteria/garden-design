$(function () {
    // pc右边边栏脚本
   $(".aside li").hover(function () {
        $(this).children(".ewm").show();
        $(this).children("div").css({"display":"block","opacity":1});
        $(this).children("div").animate({ "right": "60px"});
    }, function () {
        $(this).children(".phone_meassage").animate({ "display": "none", "opacity":0,"right": "-240px" });
        $(this).children(".qq_online").animate({ "right": "-127px", "display": "none","opacity":0 });
        $(this).children(".ewm").hide();
        $(this).children(".search_box").animate({ "right": "-205px", "display": "none","opacity":0  });
    })
    $("#goTopBtn").click(function () {
        $('body,html').animate({ scrollTop: 0 }, 600);
    })
     $(".top").click(function () {
        $('body,html').animate({ scrollTop: 0 }, 600);
    })
	
	// pc导航下拉
    $(".menu>li").hover(function () {
        $(this).find(".menu_2").slideDown(200);
    }, function () {
        $(this).find(".menu_2").hide();
    })
	
    // 手机左边弹出导航
    $(".menu_icon,.navigation").click(function () {
        $(".black_cloth").show();
        $(".wap_menu").animate({ "left": "0" }, 200);
        $("body").animate({ "left": "250px" }, 200);
        $("body").css("overflow", "hidden");
        $(".wrap_footer").animate({ "left": "250px" }, 200);
    })
    // 手机左边弹出导航，点击一级分类展开二级分类
    $(".wap_menu>li.menu_lists>.wap_menu1>p.right").click(function () {
        if ($(this).parent().siblings(".wap_menu2").css("display") == "block") {
            $(this).parent().siblings(".wap_menu2").slideUp();
            $(this).find("a").html("+");
            return;
        }
		 $(".wap_menu>li.menu_lists>.wap_menu1>p.right a").html("+");
        $(".wap_menu2").slideUp();
         $(this).find("a").html("-");
        $(this).parent().siblings(".wap_menu2").slideDown();
    })

    //手机点击打叉和黑色空白地方，关闭左边弹出菜单
    $(".menu_tit span,.black_cloth").click(function(){
				 $(".wap_menu>li.menu_lists>.wap_menu1>p.right a").html("+");
        $(".wap_menu2").slideUp();

         $(".black_cloth").hide();
        $(".wap_menu").animate({ "left": "-250" }, 200);
        $("body").animate({ "left": "0" }, 200);
        $("body").css("overflow", "visible");
        $(".wrap_footer").animate({ "left": "0" }, 200);
    })
 
    //手机底部点击搜索
    $(".w_searchButton").click(function () {
        var width = $(".wap_footer").width();
        if ($(".wap_search_input").css("left") == width + "px") {
            $(".wap_search_input").animate({ "left": 0 }, 300);
        } else {
            $(".wap_search_input").animate({ "left": "100%" }, 300);
        }
    })
	
	  //手机点击“分类”，二级分类展开显示
	   var n = 0;
    $(".phone-menuicon").click(function () {
        $(".phone-menulist").slideToggle(200);
        n++;
        //$(this).find("img").css("transform", "rotate(" + 180 * n + "deg)");
    })
	//pc和手机内页侧边栏分类，点击展开下一级 

    $("ul.sidemenu li a").click(function () {
	  $(this).parent().siblings().find("ul").slideUp()//如果要点击其他缩上去则增加这句
      $(this).next("ul").slideToggle(300);
    })
	
    
  
    //当前选中项的所有父节点都显示出来，程序会将点击的li项默认添加.current
    $("ul.sidemenu li.current").parents().show();
	
	
   //pc+wap大图js  
	 $('#owl-demo').owlCarousel({
                items: 1,
                loop:true,
				autoPlay: 100,
				autoplay:true,
        autoplayTimeout:5000,
        autoplayHoverPause:true
              });
			  
	//pc+wap产品详情图片轮播
	if($(".product_detail_images .product_detail_img .product_detail_pic").length>1){
	$(".product_detail_images .product_detail_img").addClass("owl-carousel").attr('id',"owl-demo1");
	  $('#owl-demo1').owlCarousel({
                items: 1,
        autoplayTimeout:5000,
        autoplayHoverPause:true
		});
			
	//	$(".product_detail_images .owl-item").each(function(){
	//		var img_mar=Math.floor(($(".product_detail_images .owl-stage-outer").height()-$(this).find("img").height())*0.5)
	//		
	//		$(this).find("img").css("margin-top",img_mar)
	//		})
	     var tit=$(".product_detail .title h3").text()
		 $(".product_detail_images .product_detail_img .product_detail_pic").each(function(i){
		 var len=$(".product_detail_images .product_detail_img .product_detail_pic").length;
		 var num=i+1;
		 $(this).find("a").attr("data-footer","<span>"+tit+"</span>"+"<i>"+num+"/"+len+"</i>")
		 })
		 	$(document).on('click', '[data-toggle="lightbox"]', function(event) {
                event.preventDefault();
                $(this).ekkoLightbox({
					wrapping:false,
               
               
                
					});
            });	
		window.onload=function(){
	
	 $(".product_detail_images .owl-item").each(function(){
            var img_mar=Math.floor(($(".product_detail_images .owl-stage-outer").height()-$(this).find("img").height())*0.5)
            $(this).find("img").css("margin-top",img_mar)
        })
	}
	
		}
		
	        $('div.ekko-lightbox-item').each(function () {
	            new RTP.PinchZoom($(this), {});
	        
	    })
  
   //手机底部
    if ($(window).width() < 768) {
        var height = $(".wap_footer").height() + 20;
        $(".pad").css("height", height + "px");
    }
	
	    
		
})