# WordPress Hot & Cold Database Relation Plugin
This Api is intended to help a high news able web site, he implements a relation of archiving news called "Cold" and current news called "Hot" thru the site.

**Why i need this?
 - imagine that; every day you have  10 news posts on your web site, after one month, you can have 300 posts, after 3 months 900 posts and after 6 months you can have  1800 posts registered on your database. Imagine the costs for every simple query on you web site counting, and fetching data. Imagine for example you need just last 10 more recents and most comments posts ... you can pass a date and a orderby on your query but you really need fetch this in the middle of 1800 posts?  it will be very less cost if you  search this in the middle of 900 posts located on "Hot" table.
 -This is what Hot&Cold Plugin do! he create a table called hot from you "wp_posts" and fetch only last 3 months of data... after that he redirect every query to this table and of course he maintains synchronized with wp_posts, every WordPress function will work normally.

 
