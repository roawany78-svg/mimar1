SET NOCOUNT ON;
GO

/***********************************
 1. Users
***********************************/
CREATE TABLE dbo.[User] (
    user_id      INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
    name         NVARCHAR(200)    NOT NULL,
    email        NVARCHAR(320)    NOT NULL UNIQUE, 
    password     NVARCHAR(512)   NOT NULL,         
    phone        NVARCHAR(50)     NULL,
    role         NVARCHAR(20)     NOT NULL,         -- 'client' | 'contractor' | 'admin'
    created_at   DATETIME2(3)     NOT NULL DEFAULT SYSUTCDATETIME(),
    CONSTRAINT CK_User_Role CHECK (role IN ('client','contractor','admin'))
);
CREATE INDEX IX_User_role ON dbo.[User](role);
GO

/***********************************
 2. Project
    - client_id -> User(user_id)
***********************************/
CREATE TABLE dbo.Project (
    project_id   INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
    title        NVARCHAR(300)    NOT NULL,
    description  NVARCHAR(MAX)    NULL,
    location     NVARCHAR(300)    NULL,
    status       NVARCHAR(60)     NOT NULL DEFAULT('open'),  
    created_at   DATETIME2(3)     NOT NULL DEFAULT SYSUTCDATETIME(),
    client_id    INT              NOT NULL,
    CONSTRAINT FK_Project_Client FOREIGN KEY (client_id)
        REFERENCES dbo.[User](user_id)
        ON UPDATE NO ACTION
        ON DELETE NO ACTION  -- keep project if client record removed; 
);
CREATE INDEX IX_Project_client ON dbo.Project(client_id);
CREATE INDEX IX_Project_status ON dbo.Project(status);
GO

/***********************************
 3. Attachment
    - attachments belong to a project
***********************************/
CREATE TABLE dbo.Attachment (
    attachment_id INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
    file_name     NVARCHAR(400) NOT NULL,
    file_type     NVARCHAR(100) NULL,
    uploaded_at   DATETIME2(3) NOT NULL DEFAULT SYSUTCDATETIME(),
    project_id    INT           NOT NULL,
    CONSTRAINT FK_Attachment_Project FOREIGN KEY (project_id)
        REFERENCES dbo.Project(project_id)
        ON DELETE CASCADE     -- when a project is removed, its attachments should be removed
        ON UPDATE NO ACTION
);
CREATE INDEX IX_Attachment_project ON dbo.Attachment(project_id);
GO

/***********************************
 4. Offer
    - contractor places offers for a project
***********************************/
CREATE TABLE dbo.Offer (
    offer_id      INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
    price         DECIMAL(12,2)    NOT NULL,     
    duration      NVARCHAR(100)    NULL,        
    notes         NVARCHAR(MAX)    NULL,
    created_at    DATETIME2(3)     NOT NULL DEFAULT SYSUTCDATETIME(),
    project_id    INT              NOT NULL,
    contractor_id INT              NOT NULL,
    CONSTRAINT FK_Offer_Project FOREIGN KEY (project_id)
        REFERENCES dbo.Project(project_id)
        ON DELETE CASCADE,             -- if project removed, remove offers
    CONSTRAINT FK_Offer_Contractor FOREIGN KEY (contractor_id)
        REFERENCES dbo.[User](user_id)
        ON DELETE NO ACTION
);
CREATE INDEX IX_Offer_project ON dbo.Offer(project_id);
CREATE INDEX IX_Offer_contractor ON dbo.Offer(contractor_id);
GO

/***********************************
 5. Message
    - messages exchanged by users about a project
***********************************/
CREATE TABLE dbo.Message (
    message_id    INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
    content       NVARCHAR(MAX)    NOT NULL,
    sent_at       DATETIME2(3)     NOT NULL DEFAULT SYSUTCDATETIME(),
    sender_id     INT              NOT NULL,
    receiver_id   INT              NOT NULL,
    is_read       BIT              NOT NULL DEFAULT 0,
    CONSTRAINT FK_Message_Sender FOREIGN KEY (sender_id)
        REFERENCES dbo.[User](user_id)
        ON DELETE NO ACTION,
    CONSTRAINT FK_Message_Receiver FOREIGN KEY (receiver_id)
        REFERENCES dbo.[User](user_id)
        ON DELETE NO ACTION

);
CREATE INDEX IX_Message_sender ON dbo.Message(sender_id);
CREATE INDEX IX_Message_receiver ON dbo.Message(receiver_id);
GO

/***********************************
 6. Rating
    - client gives a rating for contractor when project completed
***********************************/
CREATE TABLE dbo.Rating (
    rating_id     INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
    stars         TINYINT          NOT NULL CHECK (stars BETWEEN 1 AND 5),
    comment       NVARCHAR(MAX)    NULL,
    created_at    DATETIME2(3)     NOT NULL DEFAULT SYSUTCDATETIME(),
    rated_by      INT              NOT NULL,   
    contractor_id INT              NOT NULL,   

    CONSTRAINT FK_Rating_RatedBy FOREIGN KEY (rated_by)
        REFERENCES dbo.[User](user_id)
        ON DELETE NO ACTION,
    CONSTRAINT FK_Rating_Contractor FOREIGN KEY (contractor_id)
        REFERENCES dbo.[User](user_id)
        ON DELETE NO ACTION
);
CREATE INDEX IX_Rating_contractor ON dbo.Rating(contractor_id);
GO

/***********************************
 7. Notification
    - system notifications to users
***********************************/
CREATE TABLE dbo.Notification (
    notification_id INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
    content         NVARCHAR(MAX)    NOT NULL,
    is_read         BIT              NOT NULL DEFAULT 0,
    created_at      DATETIME2(3)     NOT NULL DEFAULT SYSUTCDATETIME(),
    user_id         INT              NOT NULL,
    CONSTRAINT FK_Notification_User FOREIGN KEY (user_id)
        REFERENCES dbo.[User](user_id)
        ON DELETE CASCADE  -- if user removed, drop notifications

);
CREATE INDEX IX_Notification_user ON dbo.Notification(user_id);
GO

/***********************************
 8. Material
    - contractor posts materials on their profile
***********************************/
CREATE TABLE dbo.Material (
    material_id   INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
    name          NVARCHAR(300)     NOT NULL,
    price         DECIMAL(12,2)     NULL,
    unit          NVARCHAR(80)      NULL,
    created_at    DATETIME2(3)      NOT NULL DEFAULT SYSUTCDATETIME(),
    added_by      INT               NOT NULL, 
    CONSTRAINT FK_Material_AddedBy FOREIGN KEY (added_by)
        REFERENCES dbo.[User](user_id)
        ON DELETE NO ACTION
);
CREATE INDEX IX_Material_added_by ON dbo.Material(added_by);
GO


/***********************************
 checks
***********************************/
PRINT 'Schema created successfully.';
GO
