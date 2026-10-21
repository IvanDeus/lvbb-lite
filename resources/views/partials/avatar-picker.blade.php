{{-- $current is the preset to preselect, or null to leave the choice explicit. --}}
<fieldset class="avatar-picker">
  <legend>Avatar</legend>
  @error('avatar')<p class="errorlist">{{ $message }}</p>@enderror
  <span class="avatars">
    @foreach (\App\Models\User::AVATAR_PRESETS as $preset)
      <label>
        <input type="radio" name="avatar" value="{{ $preset }}" @checked(old('avatar', $current) === $preset)>
        <img src="{{ asset('avatars/'.$preset) }}" alt="{{ $preset }}" width="48" height="48">
      </label>
    @endforeach
  </span>
  @error('avatar_file')<p class="errorlist">{{ $message }}</p>@enderror
  <label for="avatar_file">Or upload your own &mdash; JPG, PNG or WebP up to 2 MB, cropped to 100&times;100.
    An upload replaces the picture picked above.</label>
  <input class="field" type="file" name="avatar_file" id="avatar_file"
         accept="image/jpeg,image/png,image/webp">
</fieldset>
