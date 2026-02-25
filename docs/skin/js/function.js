
$(document).ready(function(){
	//右侧
	    $(".aside li").hover(function () {
	        $(this).find(".img1").hide();
	        $(this).find(".img2").show()
	        $(this).children(".ewm").show();
	        $(this).children(".kuzx").css({ "display": "block", "opacity": 1 });
	        $(this).children("div").animate({ "right": "60px" });
	    }, function () {
	        $(this).find(".img2").hide();
	        $(this).find(".img1").show();
	        $(this).children(".phone_meassage").animate({ "right": "-240px" });
	        $(this).children(".kuzx").animate({ "right": "-127px", "display": "none", "opacity": 0 });
	        $(this).children(".fx").animate({ "right": "-127px" });
	        $(this).children(".ewm").hide();
	        $(this).children(".ss").animate({ "right": "-205px" });
	    })
	
	
   $(".menu>li:not(':last')").each(function(){
	   $(this).prepend("<i>/</i>")
	   })	
			//PC添加视差动画
	if($(window).width()>1000){


		$(".in_image ul li").hover(function(){
				$(this).find(".text_area").animate({"height":"250px","background-positionY":"0"},200)
				},function(){
				$(this).find(".text_area").animate({"height":"150px","background-positionY":"-350px"},200)	
					})

		$(".article .list_image ul li").hover(function(){
			$(this).find(".text_area").animate({"paddingTop":"5px","background-positionY":"0"},0)
		},function(){
			$(this).find(".text_area").animate({"paddingTop":"20px","background-positionY":"-350px"},0)
		})
	}
		 
		$(".in_image ul li").each(function(i){
			 $(this).find(".text_area a").prepend("<i>0"+(i+1)+"</i>")
			})
			
			
		 $(".in_image1 .image_box ul li ").hover(function(){
				$(this).find("p").stop().show("slow")
		 },function(){
			 $(this).find("p").stop().hide("slow")
		 })

		$("#content .new .list ul .on").find(".deta").show("200")

		$("#content .new .list ul li").hover(function(){
			$("#content .new .list ul li").removeClass("on").find(".deta").stop().hide("200");
			$(this).addClass("on").find(".deta").stop().show("200")
		})

})