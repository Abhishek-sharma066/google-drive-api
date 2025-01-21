$(document).ready(function() {
    let currentFolderId = 'root'; // Start from the root folder
    let history = []; // To store navigation history for the back button

    const $fileList = $('#file-list');
    const $backButton = $('#back-button');
    const $mainDirButton = $('#main_dir');
    const $createFolderButton = $('#create_folder');
    const $createFileButton =  $('#create_file');
    const $modal = $('#modal');
    const $inputName = $('#input-name');
    const $createBtn = $('#create-btn');
    const $cancelBtn = $('#cancel-btn');
    const $modalTitle = $('#modal-title');
    const $delete_button = $('.delete_folder_file');

    // Function to load files and folders from Google Drive
    function loadFiles(folderId) {
        $fileList.html('<p>Loading...</p>');
        $backButton.hide();
        $mainDirButton.hide(); 

        $.ajax({
            url: 'google-drive.php',
            method: 'POST',
            data: { folderId, action: 'load_files' },
            success: function(response) {
                const files = JSON.parse(response);
                if (files.error) {
                    $fileList.html('<p>' + files.error + '</p>');
                    return;
                }
                $fileList.empty();

                files.files.forEach(file => {
                    let fileElement = '';
                    if (file.mimeType === 'application/vnd.google-apps.folder') {
                        // Folder is represented as a clickable container
                        fileElement = `
                            <div class="folder" data-id="${file.id}">
                                <div class="folder-header">
                                    <span class="folder-icon"><i class="fa fa-folder" aria-hidden="true" style="color:#ffc109; font-size:30px;"></i></span>
                                    <span class="folder-name">${file.name}</span>
                                </div><div><i data-id= "${file.id}"  class=" delete_folder_file fa fa-trash" aria-hidden="true" style="color:red; font-size:20px; padding: 5px 10px;"></i></div>
                            </div>
                        `;
                    } else {
                        // For files, make the entire row clickable to open in a new tab
                        const fileUrl = `https://drive.google.com/file/d/${file.id}/view`;
                        fileElement = `
                            <div class="file" data-url="${fileUrl}">
                                <span>${file.name}</span>
                                <div><i data-id= "${file.id}" class=" delete_folder_file fa fa-trash" aria-hidden="true" style="color:red; font-size:20px; padding: 5px 10px;"></i></div>
                            </div>
                        `;
                    }
                    $fileList.append(fileElement);
                });

                if (history.length > 0) {
                    $backButton.show();
                }

                if (folderId !== 'root') {
                    $mainDirButton.show();
                } else {
                    $mainDirButton.hide();
                    $backButton.hide();
                }
            }
        });
    }

    // Back button click handler
    $backButton.click(function() {
        if (history.length > 0) {
            currentFolderId = history.pop(); 
            loadFiles(currentFolderId); 
        }
        currentFolderId = "root";
    });

    // Main directory button to go back to the root folder
    $mainDirButton.click(function() {
        $mainDirButton.hide();
        currentFolderId = "root";
        loadFiles('root'); 
    });

  
    $(document).on('dblclick', '.folder', function() {
        const folderId = $(this).data('id');
        history.push(currentFolderId);
        currentFolderId = folderId;
        loadFiles(folderId); 
        $backButton.show(); 
        $mainDirButton.show(); 
    });

   
    $(document).on('dblclick', '.file', function() {
        const fileUrl = $(this).data('url'); 
        window.open(fileUrl, '_blank'); 
    });

    // creating a new folder
    $createFolderButton.click(function() {
        $modalTitle.text('Create New Folder');
        $inputName.val(''); 
        $createBtn.data('action', 'create_folder'); 
        $modal.show(); 
    });

    // creating a new file
    $createFileButton.click(function() {
        $modalTitle.text('Create New File');
        $inputName.val(''); 
        $createBtn.data('action', 'create_file'); 
        $modal.show(); 
    });

    // Create button click handler inside the modal
    $createBtn.click(function() {
        const name = $inputName.val().trim();
        if (name) {
            $.ajax({
                url: 'google-drive.php',
                method: 'POST',
                data: {
                    folderId: currentFolderId,
                    action: $createBtn.data('action'),
                    name: name
                },
                success: function(response) {
                    // Close the modal and reload the files
                    $modal.hide();
                    loadFiles(currentFolderId); // Refresh the file list
                }
            });
        } else {
            alert('Please enter a name for the folder or file.');
        }
    });

    // delete button functionality
$(document).on('click', '.delete_folder_file', function() {
    const deleteId = $(this).data('id');  // Get the data-id of the clicked item

    // Show SweetAlert confirmation dialog
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'No, cancel!'
    }).then((result) => {
     
        if (result.isConfirmed) {
            // Proceed with AJAX request to delete the file/folder If the user confirms the deletion
            $.ajax({
                url: 'google-drive.php',
                type: 'POST',
                data: {
                    action: 'delete_file_folder',
                    delete_id: deleteId
                },
                success: function(response) {
                    if (typeof response === 'string') {
                        response = JSON.parse(response);
                    }
                    if (response.success) {
                        loadFiles(currentFolderId);
                        Swal.fire(
                            'Deleted!',
                            'Your file/folder has been deleted.',
                            'success'
                        );
                    } else {
                        Swal.fire(
                            'Error!',
                            'There was a problem deleting your file/folder.',
                            'error'
                        );
                    }
                },
                error: function(xhr, status, error) {
                    
                    Swal.fire(
                        'Error!',
                        'There was a problem deleting your file/folder.',
                        'error'
                    );
                }
            });
        } else {

            console.log('User canceled deletion.');
        }
    });
});

    // Cancel button click handler inside the modal
    $cancelBtn.click(function() {
        $modal.hide(); 
    });

    // Initially load the root directory
    loadFiles('root');
});
