<!DOCTYPE html>
<html>
<head>
    <title>Activation Email</title>
</head>
<body>
    <div style="border:2px solid black; border-radius:10px; background-color:#55C227; 
                padding:0px 10px 10px 10px; text-align:center; color:white;">
        <h1 style="text-decoration:underline;">{{ $details['title'] }}</h1>
        <img src="https://i.imgur.com/Ay4kuvR.jpg"/>
        <p>{{ $details['body'] }}</p>
        <p style="font-size:16pt;fold-weight:bold; border:2px solid white;">{{ $details['code'] }}</p>
        <p>Thanks for using our application.  :-)</p>
    </div>
</body>
</html>