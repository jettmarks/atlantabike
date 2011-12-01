Travel Log Module - BETA Release

This module allows a users Travel Logs that track mileage for commutes, cyclist training, road trips etc.  The original intent for this module was to create a cyclist log, but I made it more generic and flexible so that it can be adapted for other uses, such as commuter logs, carbon footprint calculation etc.

I created normalized tables, which seems to be an exception in drupal development, perhaps for performance reasons.  But I saw an opportunity to provide all kinds of reporting and anlysis (Business Intelligence background) to provide added value to the community.

Here's a description of what this module does

Travel Log

Core functionality.  Create and manage multiple travel logs per user.  Each user can manage his/her own logs.

If using the Open Flash Chart API, users will see a bar chart showing a summary of their total monthly mileage.

Travel Log Entries

Core functionality Each Travel Log has a list of entries.  Say for example, To and From work.  You can track the mileage, duration, date and provide a name and description for each log entry.

Travel Log Rides

If you have multiple bikes, cars, motorcycles.  You can track how many miles you put on a certain bike.  Say for example, if you have a road bike and a mountain bike.  

Travel Log Types

You can many different types.  The default types are Cyclist and Commuter log types.  Again, the original intent was to create a cyclist log, but it can be extended for other uses as well.

Travel Log Groups

You can have many different groups of users.  Say for example, if you are trying to promote cylcing through competition between different departments or buildings in the place you work.  Or maybe between neighboorhoods or clubs. Administer Travel Log permissions required.

Travel Log User Group mapping

This screen allows you to set up which users belong to what group.  A user is a drupal user that has created an account on your drupal site. Administer Travel Log permissions required.

Travel Log Dashboard

Provides a summary of mileage traveled for the groups that are set up.  This is going to be expanded to provide graphical and tabular summaries of the mileage so far. 

*** Requirements and Dependencies ***

Adobe Flash 9+
Javascript Enabled on the client.

For Dashboard and summary charts, you will need the Open Flash Chart API module and the Open Flash Chart movie files.  See credits

*** Steps for installation ***

   1. Download this module (this will be added to drupal modules once/if maintainer privileges are granted)
   2. Unzip the package
   3. Copy the travellog folder to the modules folder
   4. Install the module
   (optional this can be used without the following dependencies)
   5. Follow the directions for the Open Flash Chart API to download and install module

*** Credits ***

Open Flash Chart written by John Glazebrook, http://www.teethgrinder.co.uk.
Module adapted by Adam Moore, UC Merced http://www.ucmerced.edu

*** Future ***

Since I'm new to drupal development, my heads already spinning with ideas.

1. Leverage the node or blog api so that users can create log entries and blog about each log entry
2. Leverage the taxonomy api so that users can tag their logs and log entries
3. Expand the fields in travel log entries so that users can input start / end dates and times to do some time of use histograms
4. Leverage any of the other modules that allow for images to be imbedded so that users can upload images of their cycling experiences
