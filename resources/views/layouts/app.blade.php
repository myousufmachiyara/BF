<!DOCTYPE html>
<html lang="en" class="fixed js flexbox flexboxlegacy no-touch csstransforms csstransforms3d no-overflowscrolling webkit chrome win js no-mobile-device custom-scroll sidebar-left-collapsed">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>@yield('title', 'Default Title')</title>
        <link rel="shortcut icon" href="{{ asset('assets/img/favicon.png') }}">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />

		<!-- Web Fonts  -->
		<link href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700,800|Shadows+Into+Light" rel="stylesheet" type="text/css">

		<!-- Vendor CSS -->
        
		<link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/css/bootstrap.css') }}" />
		<link rel="stylesheet" href="{{ asset('/assets/vendor/animate/animate.compat.css') }}" />
		<link rel="stylesheet" href="{{ asset('/assets/vendor/font-awesome/css/all.min.css') }}" />
		<link rel="stylesheet" href="{{ asset('/assets/vendor/boxicons/css/boxicons.min.css') }}" />
		<link rel="stylesheet" href="{{ asset('/assets/vendor/magnific-popup/magnific-popup.css') }}" />
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css" />
		<link rel="stylesheet" href="{{ asset('/assets/vendor/datatables/media/css/dataTables.bootstrap5.css') }}" />
		<link rel="stylesheet" href="{{ asset('/assets/vendor/select2/css/select2.css') }}" />
		<link rel="stylesheet" href="{{ asset('/assets/vendor/select2-bootstrap-theme/select2-bootstrap.min.css') }}" />
		<link rel="stylesheet" href="{{ asset('/assets/vendor/bootstrap-multiselect/css/bootstrap-multiselect.css') }}" />
		<link rel="stylesheet" href="{{ asset('/assets/vendor/dropzone/basic.css') }}"/>
		<link rel="stylesheet" href="{{ asset('/assets/vendor/dropzone/dropzone.css') }}" />
		<!-- Theme CSS -->
        <link rel="stylesheet" href="{{ asset('/assets/css/theme.css') }}" />
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

		<!-- Skin CSS -->
        <link rel="stylesheet" href="{{ asset('/assets/css/skins/default.css') }}" />

		<!-- Theme Custom CSS -->
        <link rel="stylesheet" href="{{ asset('/assets/css/custom.css') }}" />
        <style>
            #loader {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(255, 255, 255, 0.8);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 9999;
            }
            #loader.hidden {
                display: none;
            }
            .cust-pad {
                padding-top: 0; /* or any other default padding */
            }
            @media (min-width: 768px) {
                .cust-pad {
                    padding: 60px 10px 0px 20px;
                }
                .home-cust-pad {
                    padding: 60px 15px 0px 15px;
                }
                .sidebar-logo{
                    width:60%;
                    height:auto;
                    padding-top: 5px;
                }	
            }
            @media (max-width: 767px) {
                .sidebar-logo {
                    height: 40%;
                }
            }
            .icon-container {
                background-size: auto; /* Adjust the size of the icon to fit within the div */
                background-repeat: no-repeat; /* Ensure the icon doesn't repeat */
                background-position: right bottom; /* Align the icon to the center-right */
            }
        </style>
    </head>
    <body>
        <div id="loader">
            <div class="spinner-border" role="status">
                <span class="sr-only">Loading...</span>
            </div>
        </div>
        
        <div id="changePassword" class="zoom-anim-dialog modal-block modal-block-danger mfp-hide">
            <form id="changePasswordForm" method="post" style="width: 75%" enctype="multipart/form-data" onkeydown="return event.key != 'Enter';">
                @csrf
                <header class="card-header">
                    <h2 class="card-title">Change Password</h2>
                </header>
                <div class="card-body">
                    <div class="row form-group">    
                        <div class="col-12 mb-2">
                            <label>Current Password</label>
                            <input type="password" class="form-control" placeholder="Current Password" id="current_password" name="current_password" required>
                        </div> 
                        <div class="col-12 mb-2">
                            <label>New Password</label>
                            <input type="password" class="form-control" placeholder="New Password" id="new_password" minlength="8" name="new_password" required>
                        </div>
                        <div class="col-12 mb-2">
                            <label>Confirm New Password</label>
                            <input type="password" class="form-control" placeholder="Confirm New Password" minlength="8" id="confirm_new_password" required>
                        </div>
                    </div>
                </div>
                <footer class="card-footer">
                    <div class="row">
                        <div class="col-md-12 text-end">
                            <button type="submit" class="btn btn-primary">Change Password</button>
                            <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
                        </div>
                    </div>
                </footer>
            </form>
        </div>

        <header class="page-header">
            <div class="logo-container d-none d-md-block" style="float:right !important;">
                <button id="syncCache" class="btn btn-danger btn-sm me-3 mt-2" title="Sync & Clear Cache">
                    <i class="bx bx-sync"></i> <span class="d-none d-xl-inline-block">Sync</span>
                </button>
                <div id="userbox" class="userbox">
                    
                    <a href="#" data-bs-toggle="dropdown" style="margin-right: 20px;">
                        <div class="profile-info"> 
                            <span class="name">{{session('user_name')}}</span>
                            <span class="role">{{session('role_name')}}</span>
                        </div>
                        <i class="fa custom-caret"></i>
                    </a>
                    <div class="dropdown-menu" >
                        <ul class="list-unstyled">
                            <li>
                                <a role="menuitem" tabindex="-1" href="#changePassword" class="mb-1 mt-1 me-1 modal-with-zoom-anim ws-normal"><i class="bx bx-lock"></i> Change Password</a>
                            </li>
                            <li>	
                                <form action="/logout" method="POST">
                                    @csrf
                                    <button style="background: transparent;border: none;font-size: 14px;" type="submit" role="menuitem" tabindex="-1"><i class="bx bx-power-off"></i> Logout</button>
                                </form>
                            </li>
                            <!-- <li>
                                <a role="menuitem" tabindex="-1"><i class="bx bx-cloud-download"></i> DB Backup</a>
                            </li>
                            <li>
                                <a role="menuitem" tabindex="-1"><i class="bx bx-file"></i> Files Backup</a>
                            </li> -->
                        </ul>
                    </div>
                </div>
            </div>

            <div class="logo-container d-md-none">
                <div id="userbox" class="userbox" style="float:right !important;">
                    
                    <a href="#" data-bs-toggle="dropdown" style="margin-right: 20px;">
                        <div class="profile-info"> 
                            <span class="name">{{session('user_name')}}</span>
                            <span class="role">{{session('role_name')}}</span>
                        </div>
                        <i class="fa custom-caret"></i>
                    </a>
                    <div class="dropdown-menu" >
                        <ul class="list-unstyled">
                            <li>
                                <a role="menuitem" tabindex="-1" href="#changePassword" class="mb-1 mt-1 me-1 modal-with-zoom-anim ws-normal"><i class="bx bx-lock"></i> Change Password</a>
                            </li>
                            <!-- <li>	
                                <form action="/logout" method="POST">
                                    @csrf
                                    <button style="background: transparent;border: none;font-size: 14px;" type="submit" role="menuitem" tabindex="-1"><i class="bx bx-power-off"></i> Logout</button>
                                </form>
                            </li> -->
                        </ul>
                    </div>
                    <i class="fas fa-bars toggle-sidebar-left" data-toggle-class="sidebar-left-opened" data-target="html" data-fire-event="sidebar-left-opened" aria-label="Toggle sidebar"></i>
                </div>

            </div>
        </header>
        <section class="body">
            <div class="inner-wrapper cust-pad">
                @include('layouts.sidebar')
                <section role="main" class="content-body"> 
                    @yield('content')
                </section>
            </div>
        </section>
        <footer>
            @include('layouts.footer')
            <div class="text-end">  
                <div>
                Powered By
                <a target="_blank" href="https://syitrix.com/">SyiTrix</a> 
                </div>
            </div>
        </footer>
        <script>
            $('#syncCache').on('click', function() {
                const $btn = $(this);
                const originalHtml = $btn.html();
                
                // Disable button and show loading state
                $btn.prop('disabled', true).html('<i class="bx bx-sync bx-spin"></i> Syncing...');

                $.ajax({
                    url: "{{ route('admin.clear-cache') }}",
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}"
                    },
                    success: function(response) {
                        alert('Cache cleared successfully!');
                    },
                    error: function() {
                        alert('Something went wrong while clearing cache.');
                    },
                    complete: function() {
                        // Restore button state
                        $btn.prop('disabled', false).html(originalHtml);
                    }
                });
            });
        </script>
    </body>
</html>