<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Instagram Username Validation</title>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <style>
    .result {
      margin-top: 20px;
    }
    .error {
      color: red;
    }
  </style>
</head>
<body>

  <div>
    <label for="username">Enter Instagram Username: </label>
    <input type="text" id="username" placeholder="Enter Instagram username">
    <button id="checkUsername">Check Username</button>
  </div>

  <div class="result"></div>

  <script>
    // Instagram username regex validation
    var regex = new RegExp(/^(?!.*\.\.)(?!.*\.$)[^\W][\w.]{0,29}$/);

    // Function to check Instagram username validity
    function validateUsername(username) {
      return regex.test(username);
    }

    // Function to fetch Instagram user data
    function fetchInstagramData(username) {
      $.get("https://www.instagram.com/" + username + "/?__a=1")
        .done(function(response) {
          console.log(response); // Log the raw response
          
          // Check if response is valid
          if (response.graphql && response.graphql.user) {
            const user = response.graphql.user;
            // Display user information
            $('.result').html(`
              <h3>User Found: ${user.full_name}</h3>
              <img src="${user.profile_pic_url}" alt="Profile Picture" style="border-radius: 50%; width: 100px; height: 100px;">
              <p>Username: @${user.username}</p>
              <p>Posts: ${user.edge_owner_to_timeline_media.count}</p>
              <p>Followers: ${user.edge_followed_by.count}</p>
              <p>Following: ${user.edge_follow.count}</p>
            `);
          } else {
            $('.result').html('<p class="error">User not found or the account is private.</p>');
          }
        })
        .fail(function() {
          $('.result').html('<p class="error">Error fetching data. Please try again later.</p>');
        });
    }

    // Event listener for checking the username
    $('#checkUsername').click(function() {
      var username = $('#username').val().trim();

      if (username) {
        if (validateUsername(username)) {
          fetchInstagramData(username);
        } else {
          $('.result').html('<p class="error">Invalid username format. Please enter a valid Instagram username.</p>');
        }
      } else {
        $('.result').html('<p class="error">Please enter a username.</p>');
      }
    });
  </script>

</body>
</html>
