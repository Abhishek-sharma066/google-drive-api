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
        $fileList.html('<p style = "color:green;">Loading files ...</p>');
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
                                </div><div><i data-id= "${file.id}"  class="rename_file fa fa-pencil-square-o" aria-hidden="true" style="color:green; font-size:20px; padding: 5px 10px;"></i>
                                
                                <i data-id= "${file.id}"  class=" delete_folder_file fa fa-trash" aria-hidden="true" style="color:red; font-size:20px; padding: 5px 10px;"></i></div>
                            </div>
                        `; 
                    } else {
                        // For files, make the entire row clickable to open in a new tab
                        const fileUrl = `https://drive.google.com/file/d/${file.id}/view`;
                        fileElement = `
                            <div class="file" data-url="${fileUrl}">
                                <span>${file.name}</span>
                               <div><i data-id= "${file.id}"  class=" delete_folder_file fa fa-trash" aria-hidden="true" style="color:red; font-size:20px; padding: 5px 10px;"></i></div>
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

/// upload request 
$(".upload_div").click(function() {
    if ($("#fileInput").val()) {
        // If a file has already been selected, trigger form submission

        $("#uploadForm").submit();
    } else {
        // Otherwise, open the file input dialog
        $("#fileInput").click();
    }
});

// When the file input changes (user selects a file)
$("#fileInput").change(function() {
    var fileName = $(this).val().split("\\").pop(); // Get the selected file name
    if (fileName) {
        $("#fileName").text("Upload " + fileName).css("color", "green"); // Display the file name below the button
    } else {
        $("#fileName").text("Upload files").css("color", "black");;
    }
});
 // Handle form submission
 $("#uploadForm").submit(function(event) {
    event.preventDefault(); // Prevent the default form submission
    
    var formData = new FormData(this); // Create FormData object from the form
      formData.append('action', 'upload_files'); // Add action parameter to the formData
      formData.append('folderId', currentFolderId); // Append currentFolderId with a key like 'folderId'
      // Show the loader
      $("#loaderContainer").css('display', 'flex'); 

    $.ajax({
        url: 'google-drive.php',  // The URL where the file will be uploaded to
        type: 'POST',
        data: formData,           // Data sent to the server
        processData: false,       // Don't process the data
        contentType: false,       // Don't set content type header
        success: function(response) {
            $("#fileInput").val(''); // Clear the file input
            $("#fileName").text("Upload files").css("color", "black");;
            $("#loaderContainer").css('display', 'none'); 
            // Show success message with green color
            $("#status").text("Upload successful !").css('color', 'green');
                
            // Remove the status message after 5 seconds
            setTimeout(function() {
                $("#status").text('');
            }, 5000);
            loadFiles(currentFolderId);
        },
        error: function(xhr, status, error) {
            $("#loaderContainer").css('display', 'none'); 
             // Show error message with red color
             $("#status").text("There was an error uploading the files.").css('color', 'red');
                
             // Remove the status message after 5 seconds
             setTimeout(function() {
                 $("#status").text('');
             }, 5000);
        }
    });
});

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
        $createBtn.data('action', 'create_folder',); 
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


/////////// rename folder
// edit button
$(document).on('click', '.rename_file', function(event) {
    var folderNameElement = $(this).closest(".folder").find(".folder-name");
    var folderId = $(this).data('id');
    
    // Make the folder name editable and focus on it
    folderNameElement.attr("contenteditable", true); 
    folderNameElement.focus(); 
    folderNameElement.css("border", "1px solid #ccc");
    folderNameElement.css("padding", "5px");

    folderNameElement.on("keydown", function(event) {
        if (event.key === "Enter") {
            event.preventDefault();
            saveFolderName(folderNameElement); 
        }
    });

    // Function to save folder name
    function saveFolderName(folderNameElement) {
        var newName = folderNameElement.text().trim();
        folderNameElement.attr("contenteditable", false); 

        folderNameElement.css("border", "");
        folderNameElement.css("padding", "");

        $("#loader").show(); 

        $.ajax({
            url: 'google-drive.php',
            type: 'POST',
            data: {
                action: 'rename_folder',
                folderId: folderId,
                newName: newName
            },
            success: function(response) {
                $("#loader").hide();
                var data = JSON.parse(response);
                if (data.success) {
                    // console.log("Folder name updated to: " + newName);
                    folderNameElement.text(newName);
                } else {
                    console.log("Error renaming folder: " + data.message);
                }
            },
            error: function(xhr, status, error) {
                $("#loader").hide(); 

                console.error('AJAX Error: ' + error);
            }
        });
    }
    // Close editing when user click outside the folder name section
    $(document).on("click.closeEdit", function(event) {
        if (!folderNameElement.is(event.target) && !folderNameElement.has(event.target).length) {
            saveFolderName(folderNameElement);
            $(document).off("click.closeEdit"); 
        }
    });
    event.stopPropagation();
});


    // Cancel button click handler inside the modal
    $cancelBtn.click(function() {
        $modal.hide(); 
    });

    // Initially load the root directory
    loadFiles('root');
});
