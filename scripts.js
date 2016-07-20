/**
 * Scripts for the Wikisource Category Browser.
 */

// Initialise Foundation.
$(document).foundation();

// Set up translation.
$(function () {
    tt = new ToolTranslation ( { debug:true, tool: 'ws-cat-browser',
        callback : function () {
            tt.addILdropdown ( $('#interface_language_wrapper') ) ; // This creates a <select> dropdown in the given wrapper, to dynamically change the interface language
        }
    });
});

// The main block, loading the categories and metadata.
$(function() {
    var suffix = ""
    if (lang !== "en") {
        suffix = "_"+lang;
    }
    // Get categories.
    $.get("categories"+suffix+".json", null, function(allCats){
        $(".loading").fadeOut(400, function(){
            $("#catlist").removeClass("hide").fadeIn(400);
        });

        // Get metadata.
        $.get("build.php?lang="+lang, null, function(metadata){
            $("#last-mod").text(metadata.last_modified);
            $("#total-works").text(metadata.works_count);

            // Add categories.
            var catLabel = metadata.category_label;
            var catRoot = metadata.category_root;
            addCats(allCats, $("#catlist"), allCats[catLabel+":"+catRoot], catLabel);
        });

    });
});

function addCats(allCats, $parent, cat, catLabel) {
    $.each(cat, function(i, subcat){
        var title = subcat.replace(/_/g, " ");
        if (subcat.substr(0, catLabel.length+1)==catLabel+":") {
            title = '<span>'+title.substr(catLabel.length+1)+'</span>';
        } else {
            var encodedCat = encodeURIComponent(subcat);
            title = "<a href=\"https://"+lang+".wikisource.org/wiki/"+encodedCat+"\" title='View on Wikisource'>"+title+"</a>"
                + "<a href=\"http://tools.wmflabs.org/wsexport/tool/book.php?lang="+lang+"&format=epub&page="+encodedCat+"\" class='epub'>"
                + " <img src='https://upload.wikimedia.org/wikipedia/commons/thumb/d/d5/EPUB_silk_icon.svg/15px-EPUB_silk_icon.svg.png'"
                + "     title='Download EPUB' />"
                + "</a>";
        }
        var $newItem = $("<li class='c'>"+title+"<ol class='c'></ol></li>");
        $parent.append($newItem);
        $newItem.find("span").on("click", function(){
            var $sublist = $(this).next("ol");
            if ($sublist.is(":empty")) {
                $(this).addClass("open").removeClass("closed");
                addCats(allCats, $sublist, allCats[subcat], catLabel);
            } else {
                $(this).addClass("closed").removeClass("open");
                $sublist.children().remove();
            }
        })
    });
}