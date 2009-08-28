
BASICS:::
---------
xmlParser{} is the class that parses an XML file or string.

xmlBuilder{} parses an array (like the one from xmlParser{}->get_tree()) into an XML string.

xmlCreator{} is a set of methods to create an array like what xmlParser{} creates, and feeds it to xmlBuilder{} to create
a useable XML string.


EXTERNAL LIBRARIES:
-------------------
 1.) The "arrayToPath{}" object/class is available online at http://sf.net/projects/cs-arraytopath, or via Subversion at
 "https://cs-arraytopath.svn.sourceforge.net/svnroot/cs-arraytopath/trunk".
 
 2.) All projects which utilize these XML libraries have a "/lib" directory (beneath "public_html", which holds index.php, 
 etc.) that holds all the libraries: /lib/externals/ holds directories containing these libraries.  An example layout:
 
 	/public_html/
 		index.php
 		subdir1/
 		subdir2/
 	/lib/
 		dir1/
 		externals/
 			cs-arrayToPath/
 			cs-content/
 			cs-phpxml/
 		otherFolder/
 	/templates/
		somedir1/
		x/
 
 3.) 

REMEMBER:
---------
 1.) there can be ONLY ONE root element (the first index, "ROOT_ELEMENT").
 
 2.) the parser converts tags into UPPERCASE: lowercase indexes are special (attributes, type, value)
 	a.) if an intermediate portion of the path is "value" (case insensitive), it is left as UPPERCASE
 	b.) if the last part of the path is "value" (case insensitive), it is converted to lowercase only if that path exists.
 
 3.) arrayToPath{} addresses arrays using paths, much like a unix filesystem.  In the array below, the tag with a value
 of "AA Rechargeable Battery Pack" may be addressed as "/ROOT_ELEMENT/SHOPPING-CART/ITEMS/ITEM/0/ITEM-NAME".
 
 4.) when a tag is listed multiple times within the same parent tag, it is represented as a numerically-indexed array
 beneath the duplicated tag name: this parent tag will NOT have a "type" index.  For an example, see 
 /ROOT_ELEMENT/SHOPPING-CART/ITEMS/ITEM in the array below.
 
 5.) Attribute names are left in whatever case they were in, not converted to upper or lower like others!
 

FORMAT OF ARRAYS:::
-------------------
Array
(
    [ROOT_ELEMENT] => Array
        (
            [type] => open
            [attributes] => Array
                (
                    [XMLNS] => http://this.domain.com/wherever/whatever
                    [COMMENT] => This is my comment
                )

            [SHOPPING-CART] => Array
                (
                    [type] => open
                    [ITEMS] => Array
                        (
                            [type] => open
                            [ITEM] => Array
                                (
                                    [0] => Array
                                        (
                                            [type] => open
                                            [ITEM-NAME] => Array
                                                (
                                                    [type] => complete
                                                    [value] => AA Rechargeable Battery Pack
                                                )

                                            [ITEM-DESCRIPTION] => Array
                                                (
                                                    [type] => complete
                                                    [value] => Battery pack containing four AA rechargeable batteries
                                                )

                                            [UNIT-PRICE] => Array
                                                (
                                                    [type] => complete
                                                    [attributes] => Array
                                                        (
                                                            [CURRENCY] => USD
                                                            [testValue] => this is just a Test.
                                                        )

                                                    [value] => 12.00
                                                )

                                            [QUANTITY] => Array
                                                (
                                                    [type] => complete
                                                    [value] => 1
                                                )

                                        )

                                    [1] => Array
                                        (
                                            [type] => open
                                            [ITEM-NAME] => Array
                                                (
                                                    [type] => complete
                                                    [value] => MegaSound 2GB MP3 Player
                                                )

                                            [ITEM-DESCRIPTION] => Array
                                                (
                                                    [type] => complete
                                                    [value] => Portable MP3 player - stores 500 songs
                                                )

                                            [UNIT-PRICE] => Array
                                                (
                                                    [type] => complete
                                                    [attributes] => Array
                                                        (
                                                            [CURRENCY] => USD
                                                        )

                                                    [value] => 178.00
                                                )

                                            [QUANTITY] => Array
                                                (
                                                    [type] => complete
                                                    [value] => 1
                                                )

                                        )

                                )

                        )

                )

        )

)
----------------------------------------
 