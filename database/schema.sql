IF OBJECT_ID(N'dbo.ProductApproval', N'U') IS NULL
BEGIN
 CREATE TABLE dbo.ProductApproval (
  ProductCode NVARCHAR(100) NOT NULL,
  IsApproved BIT NOT NULL CONSTRAINT DF_ProductApproval_IsApproved DEFAULT (0),
  CreatedAt DATETIME2(0) NOT NULL CONSTRAINT DF_ProductApproval_CreatedAt DEFAULT (SYSDATETIME()),
  ModifiedAt DATETIME2(0) NOT NULL CONSTRAINT DF_ProductApproval_ModifiedAt DEFAULT (SYSDATETIME()),
  CONSTRAINT PK_ProductApproval PRIMARY KEY CLUSTERED (ProductCode)
 );
END;
