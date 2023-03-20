<div style="padding: 0 40px">

  @if($user->referrer)
    <h5>Спорсор:</h5>
    <div>
        id: <b>{{ $user->referrer->id }}</b>, name: <b>{{ $user->referrer->fullname }}</b>, email: <b>{{ $user->referrer->email }}</b>
    </div>
    <br>
  @endif

  <h5>Реферальний код:</h5>
  <div>
    <b>{{ $user->referrer_code }}</b>
  </div>
  <br>

  <h5>Партнеры:</h5>
  @if($user->referrals)
  <ol>
    @foreach($user->referrals as $key => $user_2)
      <li>
        <div>
          id: <b>{{ $user_2->id }}</b>, name: <b>{{ $user_2->fullname }}</b>, email: <b>{{ $user_2->email }}</b>
        </div>
        @if($user_2->referrals)
          <ol>
            @foreach($user_2->referrals as $key => $user_3)
              <li>
                <div>
                  id: <b>{{ $user_3->id }}</b>, name: <b>{{ $user_3->fullname }}</b>, email: <b>{{ $user_3->email }}</b>
                </div>
              </li>
            @endforeach
          </ol>
        @endif
      </li>
    @endforeach
  </ol>
  @endif
  
</div>