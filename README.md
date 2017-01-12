#Merlin Hotel Scraper

I'm proud of this code because it comes with an interesting problem and it solves it very effectively. It was written in a language I have little experience with, and it's a little rough around the edges, but it runs fast and does the job. All in all this took me a couple of evenings to write.

##The problem

Merlin Entertainment Group is a company who operate some attractions, mostly theme parks, here in the UK (and I believe in the US). When booking a hotel at one of their attractions, you must check each date manually, so you can't easily compare multiple dates. There is no simple API to hit either, prices for dates seem to be baked into a page which must be generated completely on the server side.

## My solution

I decided to write a simple page scraper from scratch in PHP. The script simply makes 365 requests to the Merlin hotel page, changing the date in the query string (GET) params accordingly. As the results come back, each page is scraped, and the data is collated and saved into a MySQL database. These can then be read back, parsed and displayed later.

## Challenges

Although there were no robots.txt rules saying I couldn't do this, and I only attempted to do the scrape out of hours, Merlin must have seen my scraper as some sort of attack, as it was performing a large volume of requests at once (365 days x 5 different number of nights x ~10 hotels, or about 18,250 requests sent close together). In hindsight this probably was a bit much, so I adjusted the cron job which ran this script to only do 1 hotel and night combination at a time, split out over the week. Since my IP had already been blocked, I also had to do this through proxies. I made sure Merlin were aware it was just little old me and not some Russian attack or something :)