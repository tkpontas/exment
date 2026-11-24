@extends('exment::auth.layout') 
@section('content')
            <p class="login-box-msg" style="border-bottom: 2px solid green; padding: 0 0 5px; margin: 0px; font-weight: bold;">利用者パスワード初期化</p>
    　　<p>パスワード初期化用のURLを認証用メールアドレスに送信します。</p>
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
                        <button type="submit" class="btn btn-dropbox btn-block btn-flat submit_disabled"><span class="glyphicon glyphicon-send"></span>　初期化用URLを送信</button>
                    </div>
                    <!-- /.col -->
                </div>
            </form>
            <div style="margin:10px 0; text-align:center;">
                <p><a href="{{admin_url('auth/login')}}"><span class="glyphicon glyphicon-ok-circle" aria-hidden="true"></span> 利用者ログイン画面に戻る</a></p>
            </div>
@endsection
