@extends('exment::auth.layout') 
@section('content')
            <p class="login-box-msg">利用者パスワード初期化</p>
    
            <form action="{{ route('password.email') }}" method="post">
                <div class="form-group has-feedback {!! !$errors->has('email') ?: 'has-error' !!}">
    
                    @if($errors->has('email')) @foreach($errors->get('email') as $message)
                    <label class="control-label" for="inputError"><i class="fa fa-times-circle-o"></i>{{$message}}</label></br>
                    @endforeach @endif
    
                    <input type="text" class="form-control" placeholder="認証用メールアドレス" name="email" value="{{ old('email') }}" required>
                    <span class="glyphicon glyphicon-envelope form-control-feedback"></span>
                </div>  
                <div class="row">
                    <!-- /.col -->
                    <div class="col-xs-8 col-md-offset-2">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <button type="submit" class="btn btn-dropbox btn-block btn-flat submit_disabled">初期化用URLを送信</button>
                    </div>
                    <!-- /.col -->
                </div>
            </form>
            <div style="margin:10px 0; text-align:center;">
                <p><a href="{{admin_url('auth/login')}}">利用者ログイン画面に戻る</a></p>
            </div>
@endsection
