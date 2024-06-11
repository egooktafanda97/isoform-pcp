<!DOCTYPE html>
<html lang="en">

<head>
	<!-- Required meta tags -->
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
	<script src="https://cdn.tailwindcss.com"></script>
	<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.0/dist/cdn.min.js"></script>
	<style>
		.card-box {
			padding: 20px;
			border-radius: 3px;
			margin-bottom: 30px;
			background-color: #fff;
		}

		.file-man-box {
			padding: 20px;
			border: 1px solid #e3eaef;
			border-radius: 5px;
			position: relative;
			margin-bottom: 20px
		}

		.file-man-box .file-close {
			color: #f1556c;
			position: absolute;
			line-height: 24px;
			font-size: 24px;
			right: 10px;
			top: 10px;
			visibility: hidden
		}

		.file-man-box .file-img-box {
			line-height: 120px;
			text-align: center
		}

		.file-man-box .file-img-box img {
			height: 64px
		}

		.file-man-box .file-download {
			font-size: 32px;
			color: #98a6ad;
			position: absolute;
			right: 10px
		}

		.file-man-box .file-download:hover {
			color: #313a46
		}

		.file-man-box .file-man-title {
			padding-right: 25px
		}

		.file-man-box:hover {
			-webkit-box-shadow: 0 0 24px 0 rgba(0, 0, 0, .06), 0 1px 0 0 rgba(0, 0, 0, .02);
			box-shadow: 0 0 24px 0 rgba(0, 0, 0, .06), 0 1px 0 0 rgba(0, 0, 0, .02)
		}

		.file-man-box:hover .file-close {
			visibility: visible
		}

		.text-overflow {
			text-overflow: ellipsis;
			white-space: nowrap;
			display: block;
			width: 100%;
			overflow: hidden;
		}

		h5 {
			font-size: 15px;
		}
	</style>
</head>

<body class="bg-gray-100 w-full p-6">
	<div class="row">
		<div class="col-md-4">
			<img src="https://www.pcpexpress.com/isoform/img/pcpexpress200.png" style="width: 150px;" class="w-full" alt="">
		</div>
		<div class="col-md-4">
			<div class="flex flex-col w-full items-center justify-center mb-4">
				<h2 class="text-center font-bold mb-0 text-2xl">Selamat Datang di Pusat Dokumentasi</h2>
				<div>
					<img src="https://www.pcpexpress.com/isoform/img/isologo1.gif" style="width: 50px;" alt="">
				</div>
			</div>
		</div>
		<div class="col-md-4"></div>
	</div>
	<div class="w-full" x-data="{ 
			isSearchActive: false , 
			searchQuery: '',
			searchResults: [],
			folderId:'',
			folderName:'',
			fetchData() {
					fetch('<?= base_url('iso/search') ?>?query=' + this.searchQuery)
						.then(response => response.json())
						.then(data => {
							if (data.length > 0){
								this.searchResults = data;
							}
						});
				},
				getIcon(type) {
					// Determine the correct icon URL based on the file type
					if (type === 'docx') {
						type = 'doc';
					} else if (type === 'xlsx') {
						type = 'xls';
					}
					return 'https://coderthemes.com/highdmin/layouts/assets/images/file_icons/' + type + '.svg';
				}
			}">
		<div class=" w-full">
			<div class="bg-gray-100 ">
				<div class="container">
					<div class="row justify-content-center">
						<div class="col-md-8">

							<div class="input-group shadow rounded-lg overflow-hidden">
								<span class="input-group-text bg-white border-0">
									<i class="fas fa-search"></i>
								</span>
								<input x-model="searchQuery" @input="fetchData()" @focus="isSearchActive = true" type="text" class="form-control border-0 bg-gray-100 hover:border-none" style="height: 45px;" placeholder="Telusuri di Drive">
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="mt-5">
				<div class="container">
					<div class="row">
						<div class="col-12">
							<div class="flex bg-gray-200 pl-2 pr-2 pt-1 pb-1 rounded-md justify-between items-center">
								<div>
									<ol class="breadcrumb text-big container-p-x py-3 m-0">
										<li class="breadcrumb-item"><a class="text-blue-500" href="<?= base_url("iso") ?>">Root</a></li>
										<?= $tree ?>
									</ol>
								</div>
								<div class="flex">
									<!-- modal button upload file -->
									<?php if (!empty($this->session->userdata('user')) && in_array(4, $permission)) : ?>
										<button type="button" class="btn btn-primary btn-sm me-2" data-bs-toggle="modal" data-bs-target="#uploadFile">
											<i class="fa fa-upload mr-1"></i> Upload
										</button>
									<?php endif ?>
									<?php if (!empty($this->session->userdata('user')) && in_array(1, $permission)) : ?>
										<button type="button" class="btn btn-primary btn-sm create-folder" data-bs-toggle="modal" data-bs-target="#createFolder">
											<i class="fa fa-folder"></i> New Folder
										</button>
									<?php endif ?>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="mt-5" x-show="isSearchActive && searchQuery !== ''">
				<div class="container">
					<div class="row">
						<!-- fetch end loop result search alpine js -->
						<template x-for="result in searchResults" :key="result.id">
							<div class="col-md-3 mb-2">
								<div class="file-man-box bg-white">
									<div class="file-img-box">
										<img :src="getIcon(result.type)" alt="icon">
									</div>
									<a :href="result.paths ?? result.uri" class="file-download">
										<i class="fa fa-download"></i>
									</a>
									<div class="file-man-title">
										<h5 class="mb-0 text-overflow" x-text="result.name">

										</h5>
										<p class="mb-0"><small x-text="result.size"></small></p>
									</div>
								</div>
							</div>
						</template>
					</div>
				</div>

			</div>

			<div class="mt-5" x-show="searchQuery === ''">
				<div class="container">
					<div class="row">
						<?php foreach ($folder as $f) : ?>
							<div class="col-md-3 mb-5 h-full">
								<div class="relative">
									<div class="absolute r-0 w-full">
										<div class="w-full flex justify-end">
											<!-- dropdown update or delete -->
											<?php if (!empty($this->session->userdata('user')) && (in_array(1, $permission) || in_array(2, $permission) || in_array(3, $permission))) : ?>
												<div class="dropdown">
													<button class="btn btn-sm btn-light dropdown-toggle" type="button" id="dropdownMenuButton1" data-bs-toggle="dropdown" aria-expanded="false">
														<i class="fas fa-ellipsis-v"></i>
													</button>
													<ul class="dropdown-menu" aria-labelledby="dropdownMenuButton1">
														<?php if (!empty($this->session->userdata('user')) && in_array(1, $permission)) : ?>
															<li>
																<a class="dropdown-item edit-folder cursor-pointer" data-id="<?= $f['id'] ?>" data-name="<?= $f['name'] ?>" data-bs-toggle="modal" data-bs-target="#createFolder">
																	<i class="fa fa-edit"></i> Edit
																</a>
															</li>
														<?php endif ?>
														<?php if (!empty($this->session->userdata('user')) && in_array(3, $permission)) : ?>
															<li>
																<a class="dropdown-item edit-folder cursor-pointer" data-id="<?= $f['id'] ?>" data-name="<?= $f['name'] ?>" data-bs-toggle="modal" data-bs-target="#createFolderUpIcon">
																	<i class="fa fa-folder"></i> Edit Icon
																</a>
															</li>
														<?php endif ?>
														<?php if (!empty($this->session->userdata('user')) && in_array(2, $permission)) : ?>
															<li>
																<a class="dropdown-item folder-destory cursor-pointer" data-id="<?= $f['uuid'] ?>">
																	<i class="fa fa-trash"></i> Hapus
																</a>
															</li>
														<?php endif ?>
													</ul>
												</div>
											<?php endif ?>
										</div>
									</div>
								</div>
								<div class="bg-white hover:shadow-md p-4 rounded-lg shadow-xl">
									<div class="flex items-center mb-4">
										<img src="<?= base_url("public/icon/" . $f['icon']) ?>" class="w-10" style="height: 50px;" alt="Google Drive">
										<div class="ml-4">
											<h3 class="font-semibold">
												<?= $f['name'] ?>
											</h3>
											<a href="<?= base_url('iso/index/' . $f['uuid']) ?>" class="text-blue-500">View Folder</a>
										</div>
									</div>
									<div class="h-2 bg-gray-200 rounded-full">
										<div class="h-2 bg-blue-500 rounded-full" style="width: 0%;"></div>
									</div>
									<div class="flex justify-between text-sm mt-2">
										<span></span>
										<div>
											<span><?= $f['count_file'] ?> file</span> <span class="ml-2"><?= !empty($f['sum_file']) || $f['sum_file'] != 0 ? $f['sum_file'] . 'kb' : '' ?></span>
										</div>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
						<?php foreach ($files as $fi) : ?>
							<div class="col-md-3 mb-2">
								<div class="file-man-box bg-white">
									<?php if (!empty($this->session->userdata('user')) && in_array(5, $permission)) : ?>
										<button data-id="<?= $fi['id'] ?>" class="file-close remove-file">
											<i class="fa fa-times-circle"></i>
										</button>
									<?php endif ?>
									<div class="file-img-box">
										<?php
										$types = $fi['type'];
										if ($types == 'docx') {
											$types = 'doc';
										} elseif ($types == 'xlsx') {
											$types = 'xls';
										}
										$icon = "https://coderthemes.com/highdmin/layouts/assets/images/file_icons/" . $types . ".svg";
										?>
										<img src="<?= $icon ?>" alt="icon">
									</div>
									<?php
									$paths = !empty($fi['paths']) ? base_url($fi['paths']) : $fi['uri']
									?>
									<a href="<?= $paths ?>" class="file-download">
										<i class="fa fa-download"></i>
									</a>
									<div class="file-man-title">
										<h5 class="mb-0 text-overflow">
											<?= $fi['name'] ?>
										</h5>
										<p class="mb-0"><small>
												<?php
												$sizeInKB = $fi['size'];
												echo $sizeInKB > 1000 ? number_format($sizeInKB / 1000, 2) . ' MB' : $sizeInKB . ' KB';
												?>
											</small></p>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>




				<!-- modal crerate folder -->
				<div class="modal fade" id="createFolderUpIcon" tabindex="-1" aria-labelledby="createFolderUpIcon" aria-hidden="true">

					<div class="modal-dialog">
						<div class="modal-content">
							<div class="modal-header">
								<h5 class="modal-title" id="createFolderLabelUpdateIcon">Create</h5>
								<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
							</div>
							<div class="modal-body">
								<form action="<?= base_url('iso/createFolderIcon') ?>" method="post" enctype="multipart/form-data">
									<input type="hidden" name="folder-id">
									<input type="hidden" name="uuid" value="<?= $uuid ?? null ?>">
									<div class="mb-3">
										<label for="name" class="form-label">Icon Folder</label>
										<input type="file" class="form-control" id="icon" name="icon">
									</div>
									<button type="submit" class="btn btn-primary">Create</button>
								</form>
							</div>
						</div>
					</div>
				</div>

				<!-- modal crerate folder icon-->
				<div class="modal fade" id="createFolder" tabindex="-1" aria-labelledby="createFolderLabel" aria-hidden="true">

					<div class="modal-dialog">
						<div class="modal-content">
							<div class="modal-header">
								<h5 class="modal-title" id="createFolderLabel">Create Folder</h5>
								<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
							</div>
							<div class="modal-body">
								<form action="<?= base_url('iso/createFolder') ?>" method="post">
									<input type="hidden" name="folder-id">
									<input type="hidden" name="uuid" value="<?= $uuid ?? null ?>">
									<div class="mb-3">
										<label for="name" class="form-label">Folder Name</label>
										<input type="text" class="form-control" id="name" name="name" :value="this.folderName">
									</div>
									<button type="submit" class="btn btn-primary">Create</button>
								</form>
							</div>
						</div>
					</div>
				</div>

				<!-- uploadFile -->
				<div class="modal fade" id="uploadFile" tabindex="-1" aria-labelledby="uploadFileLabel" aria-hidden="true">
					<div class="modal-dialog">
						<div class="modal-content">
							<div class="modal-header">
								<h5 class="modal-title" id="uploadFileLabel">Upload File</h5>
								<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
							</div>
							<div class="modal-body text-center">
								<form action="<?= base_url('iso/uploadFile') ?>" method="post" enctype="multipart/form-data">
									<input type="hidden" name="uuid" value="<?= $uuid ?? null ?>">

									<div class="mb-3">
										<input type="text" name="file-name" class="form-control">
									</div>
									<div class="mb-3">
										<input type="file" name="file" class="form-control">
									</div>
									<button type="submit" class="btn btn-primary">Upload</button>
								</form>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/axios/1.7.2/axios.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.min.js"></script>

	<script>
		$(".remove-file").click(function() {
			const id = $(this).data('id');
			swal({
					title: "Anda Yakin?",
					text: "Setelah dihapus, Anda tidak akan dapat memulihkan file ini!",
					icon: "warning",
					buttons: true,
					dangerMode: true,
				})
				.then((willDelete) => {
					if (willDelete) {
						axios.get(`<?= base_url('iso/deleteFile/') ?>${id}`)
							.then(({
								data
							}) => {
								swal("File berhasil dihapus!", {
									icon: "success",
								});
								setTimeout(() => {
									location.reload();
								}, 1000);
							})
							.catch((err) => {
								console.log(err);
							})
					}
				});
		});
		// delete folder	
		$(".folder-destory").click(function() {
			const id = $(this).data('id');
			swal({
					title: "Anda Yakin?",
					text: "Setelah dihapus, Anda tidak akan dapat memulihkan folder ini!",
					icon: "warning",
					buttons: true,
					dangerMode: true,
				})
				.then((willDelete) => {
					if (willDelete) {
						axios.get(`<?= base_url('iso/deleteFolder/') ?>${id}`)
							.then(({
								data
							}) => {
								swal("Folder berhasil dihapus!", {
									icon: "success",
								});
								setTimeout(() => {
									location.reload();
								}, 1000);
							})
							.catch((err) => {
								console.log(err);
							})
					}
				});
		});

		$(".edit-folder").click(function() {
			const id = $(this).data('id');
			const name = $(this).data('name');
			$('[name="folder-id"]').val(id);
			$('[name="name"]').val(name);
			$("#createFolderLabelUpdateIcon").html(name);
		});
		// create
		$(".create-folder").click(function() {
			$('[name="folder-id"]').val('');
			$('[name="name"]').val('');
		});

		// validate icon png
		$("#icon").change(function() {
			const file = $(this).val();
			const ext = file.split('.').pop();
			if (ext !== 'png') {
				swal("Error", "Icon harus berformat png", "error");
				$(this).val('');
			}
		});
	</script>
</body>

</html>